<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('../include/connect.php');

// Check database connection
if (!$connect) {
    echo json_encode(['status' => false, 'message' => 'Database connection failed']);
    exit;
}

// Store connection details for reconnection
$host = "82.180.142.204";
$user = "u954141192_mos";
$password = "Mos@2024";
$dbname = "u954141192_mos";

// Get the date parameter
$quiz_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$gemini_key = isset($_GET['gemini_key']) ? $_GET['gemini_key'] : '';

// Simple test endpoint
if (isset($_GET['test'])) {
    echo json_encode(['status' => true, 'message' => 'Script is working', 'date' => $quiz_date]);
    exit;
}

// Validate API key
if (empty($gemini_key)) {
    echo json_encode(['status' => false, 'message' => 'Gemini API key is required']);
    exit;
}

try {
    // Check if quiz already exists for this date
    $check_query = "SELECT quiz_id FROM quizzes WHERE DATE(creation_date) = ?";
    $stmt = mysqli_prepare($connect, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $quiz_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['status' => false, 'message' => 'Quiz already exists for this date']);
        exit;
    }

    // Create prompt for AI
    $prompt = "Generate 10 multiple choice questions for 10-12th class students covering these topics: Science, Technology, Engineering, Arts, Mathematics. Include at least 1 question about recent events/tech news. Return JSON array format with question, options array, and correct_option number (1-4).";

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $gemini_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    // Execute cURL request
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // Decode the response
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from Gemini API: ' . json_last_error_msg());
    }
    
    if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Unexpected API response structure');
    }
    
    $ai_json = $responseData['candidates'][0]['content']['parts'][0]['text'];
    $ai_json = trim($ai_json);
    $ai_json = preg_replace('/^```(json)?|```$/m', '', $ai_json);
    $ai_json = trim($ai_json);
    
    $questions = json_decode($ai_json, true);
    if (!is_array($questions)) {
        throw new Exception('AI did not return valid JSON. Raw response: ' . substr($ai_json, 0, 500));
    }

    // Create quiz first (without transaction)
    $quiz_name = "Daily Quiz - " . date('d M Y', strtotime($quiz_date));
    $insert_quiz = "INSERT INTO quizzes (quiz_name, creation_date, duration_minutes) VALUES (?, ?, 30)";
    $stmt = mysqli_prepare($connect, $insert_quiz);
    mysqli_stmt_bind_param($stmt, "ss", $quiz_name, $quiz_date);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error creating quiz: " . mysqli_error($connect));
    }
    
    $quiz_id = mysqli_insert_id($connect);
    mysqli_stmt_close($stmt);

    // Insert questions one by one (no transaction)
    $inserted_count = 0;
    foreach ($questions as $index => $q) {
        if (!isset($q['question']) || !isset($q['options']) || !isset($q['correct_option']) ||
            !is_array($q['options']) || count($q['options']) !== 4 ||
            !is_numeric($q['correct_option']) || $q['correct_option'] < 1 || $q['correct_option'] > 4) {
            continue;
        }

        $question_text = mysqli_real_escape_string($connect, $q['question']);
        $option1 = mysqli_real_escape_string($connect, $q['options'][0]);
        $option2 = mysqli_real_escape_string($connect, $q['options'][1]);
        $option3 = mysqli_real_escape_string($connect, $q['options'][2]);
        $option4 = mysqli_real_escape_string($connect, $q['options'][3]);
        $correct_option = intval($q['correct_option']);

        $insert_question = "INSERT INTO questions 
            (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks, question_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, 2, 'multiple_choice')";
        
        $stmt = mysqli_prepare($connect, $insert_question);
        mysqli_stmt_bind_param($stmt, "isssssi", $quiz_id, $question_text, $option1, $option2, $option3, $option4, $correct_option);
        
        if (mysqli_stmt_execute($stmt)) {
            $inserted_count++;
        }
        
        mysqli_stmt_close($stmt);
    }
    
    echo json_encode([
        'status' => true, 
        'message' => "Quiz generated successfully with $inserted_count questions",
        'quiz_id' => $quiz_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?>
