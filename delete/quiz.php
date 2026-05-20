<?php
include '../include/session.php';
include '../include/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quizId'])) {
    $quizId = intval($_POST['quizId']);

    $connect->begin_transaction();

    try {
        // 1. Fetch all attempt IDs for this quiz
        $attempts = [];
        $attemptQuery = "SELECT attempt_id FROM user_quiz_attempts WHERE quiz_id = ?";
        $stmt = $connect->prepare($attemptQuery);
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $attempts[] = $row['attempt_id'];
        }
        $stmt->close();

        // 2. Fetch all question IDs for this quiz
        $questions = [];
        $questionQuery = "SELECT question_id FROM questions WHERE quiz_id = ?";
        $stmt = $connect->prepare($questionQuery);
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $questions[] = $row['question_id'];
        }
        $stmt->close();

        // 3. Delete from user_answers referencing the attempts
        if (!empty($attempts)) {
            $placeholders = implode(',', array_fill(0, count($attempts), '?'));
            $deleteAnswersSql = "DELETE FROM user_answers WHERE attempt_id IN ($placeholders)";
            $stmt = $connect->prepare($deleteAnswersSql);
            $stmt->bind_param(str_repeat('i', count($attempts)), ...$attempts);
            $stmt->execute();
            $stmt->close();
        }

        // 4. Delete from fill_blank_answers and user_answers referencing the questions
        if (!empty($questions)) {
            $placeholders = implode(',', array_fill(0, count($questions), '?'));
            
            // Delete fill_blank_answers
            $deleteFBAnswersSql = "DELETE FROM fill_blank_answers WHERE question_id IN ($placeholders)";
            $stmt = $connect->prepare($deleteFBAnswersSql);
            $stmt->bind_param(str_repeat('i', count($questions)), ...$questions);
            $stmt->execute();
            $stmt->close();

            // Delete user_answers referencing questions (secondary safety check)
            $deleteUserAnsByQSql = "DELETE FROM user_answers WHERE question_id IN ($placeholders)";
            $stmt = $connect->prepare($deleteUserAnsByQSql);
            $stmt->bind_param(str_repeat('i', count($questions)), ...$questions);
            $stmt->execute();
            $stmt->close();
        }

        // 5. Delete from user_quiz_attempts
        $deleteAttemptsSql = "DELETE FROM user_quiz_attempts WHERE quiz_id = ?";
        $stmt = $connect->prepare($deleteAttemptsSql);
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $stmt->close();

        // 6. Delete from questions
        $deleteQuestionsSql = "DELETE FROM questions WHERE quiz_id = ?";
        $stmt = $connect->prepare($deleteQuestionsSql);
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $stmt->close();

        // 7. Finally, delete the quiz
        $deleteQuizSql = "DELETE FROM quizzes WHERE quiz_id = ?";
        $stmt = $connect->prepare($deleteQuizSql);
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $stmt->close();

        $connect->commit();
        echo json_encode(['status' => 'success', 'message' => 'Quiz and all its related data deleted successfully.']);
    } catch (Exception $e) {
        $connect->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete Quiz: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();
?>
