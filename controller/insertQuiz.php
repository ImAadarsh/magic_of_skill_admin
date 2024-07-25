<?php
ob_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include "../include/session.php";
    include "../include/connect.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    error_log("Received POST data: " . print_r($_POST, true));

    $quizName = mysqli_real_escape_string($connect, $_POST['quiz_name'] ?? '');
    $quizDescription = mysqli_real_escape_string($connect, $_POST['quiz_description'] ?? '');
    $quizDate = mysqli_real_escape_string($connect, $_POST['quiz_date'] ?? '');
    $durationMinutes = intval($_POST['duration_minutes'] ?? 0);

    $connect->begin_transaction();

    $insertQuizQuery = "INSERT INTO quizzes (quiz_name, creation_date, duration_minutes) VALUES (?, ?, ?)";
    $stmt = $connect->prepare($insertQuizQuery);
    $stmt->bind_param("ssi", $quizName, $quizDate, $durationMinutes);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting quiz: " . $stmt->error);
    }
    $quizId = $stmt->insert_id;

    foreach ($_POST['questions'] as $question) {
        $questionText = mysqli_real_escape_string($connect, $question['text']);
        $questionType = $question['type'];
        $marks = floatval($question['marks']);

        if ($questionType === 'multiple_choice') {
            $correctOption = intval($question['correct_option'] ?? 0);

            $options = array_map(function($option) use ($connect) {
                return mysqli_real_escape_string($connect, $option);
            }, $question['options'] ?? []);

            while (count($options) < 4) {
                $options[] = '';
            }

            $insertQuestionQuery = "INSERT INTO questions (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks, question_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $connect->prepare($insertQuestionQuery);
            $stmt->bind_param("isssssids", $quizId, $questionText, $options[0], $options[1], $options[2], $options[3], $correctOption, $marks, $questionType);
        } elseif ($questionType === 'fill_blank') {
            $insertQuestionQuery = "INSERT INTO questions (quiz_id, question_text, marks, question_type) VALUES (?, ?, ?, ?)";
            $stmt = $connect->prepare($insertQuestionQuery);
            $stmt->bind_param("isds", $quizId, $questionText, $marks, $questionType);
        } else {
            throw new Exception("Invalid question type: " . $questionType);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting question: " . $stmt->error);
        }
        $questionId = $stmt->insert_id;

        if ($questionType === 'fill_blank') {
            $correctAnswer = mysqli_real_escape_string($connect, $question['correct_answer']);
            $insertAnswerSql = "INSERT INTO fill_blank_answers (question_id, correct_answer) VALUES (?, ?)";
            $stmt = $connect->prepare($insertAnswerSql);
            $stmt->bind_param("is", $questionId, $correctAnswer);
            $stmt->execute();
        }
    }

    $connect->commit();
    echo json_encode(['status' => true, 'message' => 'Quiz added successfully']);
} catch (Exception $e) {
    if (isset($connect) && $connect->connect_errno == 0) {
        $connect->rollback();
    }
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
    error_log("Quiz insertion error: " . $e->getMessage());
} finally {
    if (isset($connect) && $connect->connect_errno == 0) {
        $connect->close();
    }
}

ob_end_flush();
exit();
?>