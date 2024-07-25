<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quizId'])) {
    $quizId = intval($_POST['quizId']);

    // Start a transaction
    $connect->begin_transaction();

    try {
        // Delete associated fill_blank_answers first
        $deleteFillBlankAnswersSql = "DELETE fba FROM fill_blank_answers fba
                                      INNER JOIN questions q ON fba.question_id = q.question_id
                                      WHERE q.quiz_id = ?";
        $deleteFillBlankAnswersStmt = $connect->prepare($deleteFillBlankAnswersSql);
        $deleteFillBlankAnswersStmt->bind_param("i", $quizId);
        $deleteFillBlankAnswersStmt->execute();

        // Delete associated user_answers
        $deleteUserAnswersSql = "DELETE ua FROM user_answers ua
                                 INNER JOIN questions q ON ua.question_id = q.question_id
                                 WHERE q.quiz_id = ?";
        $deleteUserAnswersStmt = $connect->prepare($deleteUserAnswersSql);
        $deleteUserAnswersStmt->bind_param("i", $quizId);
        $deleteUserAnswersStmt->execute();

        // Delete associated questions
        $deleteQuestionsSql = "DELETE FROM questions WHERE quiz_id = ?";
        $deleteQuestionsStmt = $connect->prepare($deleteQuestionsSql);
        $deleteQuestionsStmt->bind_param("i", $quizId);
        $deleteQuestionsStmt->execute();

        // Delete associated user_quiz_attempts
        $deleteAttemptsSql = "DELETE FROM user_quiz_attempts WHERE quiz_id = ?";
        $deleteAttemptsStmt = $connect->prepare($deleteAttemptsSql);
        $deleteAttemptsStmt->bind_param("i", $quizId);
        $deleteAttemptsStmt->execute();

        // Delete the quiz
        $deleteQuizSql = "DELETE FROM quizzes WHERE quiz_id = ?";
        $deleteQuizStmt = $connect->prepare($deleteQuizSql);
        $deleteQuizStmt->bind_param("i", $quizId);
        $deleteQuizStmt->execute();

        // Commit the transaction
        $connect->commit();

        echo json_encode(['status' => 'success', 'message' => 'Quiz and all associated data deleted successfully']);
    } catch (Exception $e) {
        // Rollback the transaction if an error occurs
        $connect->rollback();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while deleting the quiz: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

$connect->close();
?>