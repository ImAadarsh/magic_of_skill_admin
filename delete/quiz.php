<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quizId'])) {
    $quizId = intval($_POST['quizId']);

    // Start a transaction
    $connect->begin_transaction();

    try {
        // Delete associated questions first
        $deleteQuestionsSql = "DELETE FROM questions WHERE quiz_id = ?";
        $deleteQuestionsStmt = $connect->prepare($deleteQuestionsSql);
        $deleteQuestionsStmt->bind_param("i", $quizId);
        $deleteQuestionsStmt->execute();

        // Delete the quiz
        $deleteQuizSql = "DELETE FROM quizzes WHERE quiz_id = ?";
        $deleteQuizStmt = $connect->prepare($deleteQuizSql);
        $deleteQuizStmt->bind_param("i", $quizId);
        $deleteQuizStmt->execute();

        // Commit the transaction
        $connect->commit();

        echo json_encode(['status' => 'success', 'message' => 'Quiz deleted successfully']);
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