<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$gemini_key = "AIzaSyArJ_NZJWUbhNe6r7L_DwXlTyXT-5kYJYs";

echo "<h2>Checking Available Gemini Models</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models?key=" . $gemini_key);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "<h3>Making API Request to list models...</h3>";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "<h3>Response Details:</h3>";
echo "HTTP Code: " . $http_code . "<br>";

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
        
        if (isset($responseData['models'])) {
            echo "<h3>Available Models:</h3>";
            foreach ($responseData['models'] as $model) {
                echo "• " . $model['name'] . "<br>";
                if (isset($model['supportedGenerationMethods'])) {
                    echo "  Supported methods: " . implode(', ', $model['supportedGenerationMethods']) . "<br>";
                }
                echo "<br>";
            }
        } else {
            echo "❌ No models found in response<br>";
        }
    }
} else {
    echo "❌ API returned HTTP " . $http_code . "<br>";
}
?>
