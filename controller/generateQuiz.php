<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
include('../include/connect.php');

// Topics array
$topics = [
    'HERITAGE' => [
        'SCIENCE' => [
            'Basic Concepts',
            'Biology',
            'Chemistry',
            'Physics',
            'Elements & Compounds',
            'Honors',
            "India's Contribution to Science",
            'Inventions'
        ],
        'TECHNOLOGY' => [
            'Communication Technology',
            'History of AI',
            "India's Contribution to Technology",
            'Modern Technology',
            'Space Technology',
            'Tech Companies & People'
        ],
        'ENGINEERING' => [
            'Aerospace Engineering',
            'Chemical Engineering',
            'Civil Engineering',
            'Computer Science',
            'Electrical & Electronics Engineering',
            "India's Contribution to Engineering"
        ],
        'ARTS' => [
            'Films & Media',
            'Folk Dances & Festivals of India',
            'Literature',
            'Pop Culture',
            'Visual Arts & Paintings'
        ],
        'MATHEMATICS' => [
            'The Ultimate Quest',
            'History of Math',
            'Math in Media',
            'Math Puzzles',
            'Math Trivia',
            'Probability, Permutation & Combination',
            'Statistics, Data Interpretation & Geometry'
        ]
    ]
];

// Get the date parameter
$quiz_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Check if quiz already exists for this date
$check_query = "SELECT quiz_id FROM quizzes WHERE DATE(creation_date) = '$quiz_date'";
$check_result = mysqli_query($connect, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    echo json_encode(['status' => false, 'message' => 'Quiz already exists for this date']);
    exit;
}

// Gemini API key
$GEMINI_API_KEY = isset($_GET['gemini_key']) ? $_GET['gemini_key'] : "KEY_NOT_FOUND"; // Replace with your actual API key

// Create prompt for AI
$prompt = "Generate 10 multiple choice questions for 10-12th class students covering the following topics:\n\n";

// Randomly select topics
$selected_topics = [];
foreach ($topics['HERITAGE'] as $category => $subtopics) {
    $random_topic = $subtopics[array_rand($subtopics)];
    $selected_topics[] = "$category: $random_topic";
}

$prompt .= implode("\n", $selected_topics) . "\n\n";
$prompt .= "REQUIREMENTS:\n";
$prompt .= "1. Generate exactly 10 multiple choice questions\n";
$prompt .= "2. Each question must have exactly 4 options\n";
$prompt .= "3. One option must be clearly correct\n";
$prompt .= "4. Mix up the position of correct answers\n";
$prompt .= "5. Questions should be challenging but appropriate for 10-12th class students\n\n";

$prompt .= "FORMAT:\n";
$prompt .= "Return a JSON array of questions. Each question should have:\n";
$prompt .= "- question: the question text\n";
$prompt .= "- options: array of 4 answer options\n";
$prompt .= "- correct_option: number 1-4 indicating which option is correct\n\n";

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $GEMINI_API_KEY);
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

try {
    // Execute cURL request
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Decode the response
    $responseData = json_decode($response, true);
    
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_json = $responseData['candidates'][0]['content']['parts'][0]['text'];
        // Clean up AI output
        $ai_json = trim($ai_json);
        $ai_json = preg_replace('/^```(json)?|```$/m', '', $ai_json);
        $ai_json = trim($ai_json);
        
        // Parse questions
        $questions = json_decode($ai_json, true);
        if (!is_array($questions)) {
            throw new Exception('AI did not return valid JSON.');
        }

        // Start transaction
        mysqli_begin_transaction($connect);

        // Create quiz
        $quiz_name = "Daily Quiz - " . date('d M Y', strtotime($quiz_date));
        $insert_quiz = "INSERT INTO quizzes (quiz_name, creation_date, duration_minutes) 
                       VALUES ('$quiz_name', '$quiz_date', 30)";
        
        if (!mysqli_query($connect, $insert_quiz)) {
            throw new Exception("Error creating quiz: " . mysqli_error($connect));
        }

        $quiz_id = mysqli_insert_id($connect);

        // Insert questions
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
                VALUES 
                ('$quiz_id', '$question_text', '$option1', '$option2', '$option3', '$option4', 
                '$correct_option', 2, 'multiple_choice')";

            if (!mysqli_query($connect, $insert_question)) {
                throw new Exception("Error inserting question: " . mysqli_error($connect));
            }
        }

        // Commit transaction
        mysqli_commit($connect);
        
        echo json_encode([
            'status' => true, 
            'message' => 'Quiz generated successfully',
            'quiz_id' => $quiz_id
        ]);

    } else {
        throw new Exception('Invalid response from AI service');
    }

} catch (Exception $e) {
    // Rollback transaction if active
    if (mysqli_get_connection_stats($connect)) {
        mysqli_rollback($connect);
    }
    
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?> 