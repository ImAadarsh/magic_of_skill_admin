<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$key = $_GET['key'];
// Load the quiz generation script
$response = file_get_contents('https://admin.magicofskills.com/controller/generateQuiz.php?date='.(date('Y-m-d', strtotime('+4 days'))).'&gemini_key='.$key);

// Log the response
$log_file = __DIR__ . '/quiz_generation.log';
$log_entry = date('Y-m-d H:i:s') . " - Response: " . $response . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

// Parse response
$result = json_decode($response, true);

if ($result['status']) {
    echo "Quiz generated successfully. Quiz ID: " . $result['quiz_id'] . "\n";
} else {
    echo "Error generating quiz: " . $result['message'] . "\n";
}
?> 