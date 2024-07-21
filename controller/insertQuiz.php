<?php
ob_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include your database connection file
    include "../include/session.php";
    include "../include/connect.php";

    // Ensure that this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Debugging: Log received data
    error_log("Received POST data: " . print_r($_POST, true));

    // Retrieve and sanitize the quiz data
    $quizName = mysqli_real_escape_string($connect, $_POST['quiz_name'] ?? '');
    $quizDescription = mysqli_real_escape_string($connect, $_POST['quiz_description'] ?? '');
    $quizDate = mysqli_real_escape_string($connect, $_POST['quiz_date'] ?? '');
    $durationMinutes = intval($_POST['duration_minutes'] ?? 0);

    // Start a transaction
    $connect->begin_transaction();

    // Insert the quiz
    $insertQuizQuery = "INSERT INTO quizzes (quiz_name, creation_date, duration_minutes) VALUES (?, ?, ?)";
    $stmt = $connect->prepare($insertQuizQuery);
    $stmt->bind_param("ssi", $quizName, $quizDate, $durationMinutes);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting quiz: " . $stmt->error);
    }
    $quizId = $stmt->insert_id;

    // Process each question
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $question) {
            $questionText = mysqli_real_escape_string($connect, $question['text'] ?? '');
            $marks = floatval($question['marks'] ?? 0);
            $correctOption = intval($question['correct_option'] ?? 0);

            // Sanitize options
            $options = array_map(function($option) use ($connect) {
                return mysqli_real_escape_string($connect, $option);
            }, $question['options'] ?? []);

            // Ensure we have 4 options
            while (count($options) < 4) {
                $options[] = '';
            }

            // Insert the question
            $insertQuestionQuery = "INSERT INTO questions (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $connect->prepare($insertQuestionQuery);
            $stmt->bind_param("isssssid", $quizId, $questionText, $options[0], $options[1], $options[2], $options[3], $correctOption, $marks);
            
            if (!$stmt->execute()) {
                throw new Exception("Error inserting question: " . $stmt->error);
            }
        }
    }

    // If everything is successful, commit the transaction
    $connect->commit();
    echo json_encode(['status' => true, 'message' => 'Quiz added successfully']);
} catch (Exception $e) {
    // If there's an error, roll back the transaction if it's active
    if (isset($connect) && $connect->connect_errno == 0) {
        $connect->rollback();
    }
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
    // Log the error
    error_log("Quiz insertion error: " . $e->getMessage());
} finally {
    // Close the database connection if it's open
    if (isset($connect) && $connect->connect_errno == 0) {
        $connect->close();
    }
}

ob_end_flush();
exit();
?>