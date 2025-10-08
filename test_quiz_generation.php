<?php
// Simple test file to debug quiz generation
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Quiz Generation</h2>";

// Test 1: Check if the script file exists
echo "<h3>Test 1: File Existence</h3>";
if (file_exists('controller/generateQuiz.php')) {
    echo "✅ generateQuiz.php exists<br>";
} else {
    echo "❌ generateQuiz.php not found<br>";
}

// Test 2: Check database connection
echo "<h3>Test 2: Database Connection</h3>";
include('include/connect.php');
if ($connect) {
    echo "✅ Database connection successful<br>";
    
    // Test if tables exist
    $tables = ['quizzes', 'questions'];
    foreach ($tables as $table) {
        $result = mysqli_query($connect, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' not found<br>";
        }
    }
} else {
    echo "❌ Database connection failed<br>";
}

// Test 3: Test the script with test parameter
echo "<h3>Test 3: Script Execution</h3>";
$test_url = "https://admin.magicofskills.com/controller/generateQuiz.php?test=1";
$response = @file_get_contents($test_url);

if ($response !== false) {
    echo "✅ Script executed successfully<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
} else {
    echo "❌ Script execution failed<br>";
    echo "Error: " . error_get_last()['message'] . "<br>";
}

// Test 4: Test with actual parameters
echo "<h3>Test 4: Full Script Test</h3>";
$full_url = "https://admin.magicofskills.com/controller/generateQuiz.php?date=2025-01-15&gemini_key=AIzaSyArJ_NZJWUbhNe6r7L_DwXlTyXT-5kYJYs";
$response = @file_get_contents($full_url);

if ($response !== false) {
    echo "✅ Full script executed<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
} else {
    echo "❌ Full script failed<br>";
    echo "Error: " . error_get_last()['message'] . "<br>";
}

echo "<h3>PHP Info</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "cURL Available: " . (extension_loaded('curl') ? 'Yes' : 'No') . "<br>";
echo "JSON Available: " . (extension_loaded('json') ? 'Yes' : 'No') . "<br>";
?>
