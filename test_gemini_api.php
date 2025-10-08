<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$gemini_key = "AIzaSyArJ_NZJWUbhNe6r7L_DwXlTyXT-5kYJYs";

echo "<h2>Testing Gemini API</h2>";

// Test 1: Simple API call
$prompt = "Generate 1 simple multiple choice question about science. Return JSON format: {\"question\": \"text\", \"options\": [\"a\", \"b\", \"c\", \"d\"], \"correct_option\": 1}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $gemini_key);
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

echo "<h3>Making API Request...</h3>";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "<h3>Response Details:</h3>";
echo "HTTP Code: " . $http_code . "<br>";
echo "cURL Error: " . curl_error($ch) . "<br>";

if (curl_errno($ch)) {
    echo "❌ cURL Error: " . curl_error($ch) . "<br>";
} else {
    echo "✅ cURL executed successfully<br>";
}

curl_close($ch);

echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($http_code == 200) {
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ JSON Parse Error: " . json_last_error_msg() . "<br>";
    } else {
        echo "✅ JSON parsed successfully<br>";
        
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_text = $responseData['candidates'][0]['content']['parts'][0]['text'];
            echo "<h3>AI Generated Text:</h3>";
            echo "<pre>" . htmlspecialchars($ai_text) . "</pre>";
            
            // Try to parse as JSON
            $cleaned = trim($ai_text);
            $cleaned = preg_replace('/^```(json)?|```$/m', '', $cleaned);
            $cleaned = trim($cleaned);
            
            echo "<h3>Cleaned Text:</h3>";
            echo "<pre>" . htmlspecialchars($cleaned) . "</pre>";
            
            $questions = json_decode($cleaned, true);
            if (is_array($questions)) {
                echo "✅ Successfully parsed as JSON array<br>";
                echo "<pre>" . print_r($questions, true) . "</pre>";
            } else {
                echo "❌ Could not parse as JSON array<br>";
                echo "JSON Error: " . json_last_error_msg() . "<br>";
            }
        } else {
            echo "❌ Unexpected response structure<br>";
            echo "<pre>" . print_r($responseData, true) . "</pre>";
        }
    }
} else {
    echo "❌ API returned HTTP " . $http_code . "<br>";
}
?>
