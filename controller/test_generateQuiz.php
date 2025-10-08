<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

try {
    include('../include/connect.php');
    
    // Check database connection
    if (!$connect) {
        throw new Exception('Database connection failed');
    }
    
    // Get parameters
    $quiz_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $gemini_key = isset($_GET['gemini_key']) ? $_GET['gemini_key'] : '';
    
    // Simple test response
    if (isset($_GET['test'])) {
        echo json_encode([
            'status' => true, 
            'message' => 'Test successful',
            'date' => $quiz_date,
            'key_provided' => !empty($gemini_key)
        ]);
        exit;
    }
    
    // Validate API key
    if (empty($gemini_key)) {
        throw new Exception('Gemini API key is required');
    }
    
    // Check if quiz already exists
    $check_query = "SELECT quiz_id FROM quizzes WHERE DATE(creation_date) = ?";
    $stmt = mysqli_prepare($connect, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $quiz_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        throw new Exception('Quiz already exists for this date');
    }
    
    // Test Gemini API call
    $prompt = "Generate 2 simple multiple choice questions about science for 10th grade students. Return JSON format with question, options array, and correct_option number.";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $gemini_key);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('API returned HTTP ' . $http_code . ': ' . $response);
    }
    
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response: ' . json_last_error_msg());
    }
    
    if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Unexpected API response structure');
    }
    
    $ai_text = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    echo json_encode([
        'status' => true,
        'message' => 'API call successful',
        'response_length' => strlen($ai_text),
        'sample_response' => substr($ai_text, 0, 200) . '...'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?>
