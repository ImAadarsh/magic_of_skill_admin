<?php
include 'include/session.php';
include 'include/connect.php';

$quizId = $_GET['id'] ?? 0;
// Fetch quiz details
$quizSql = "SELECT * FROM quizzes WHERE quiz_id = ?";
$quizStmt = $connect->prepare($quizSql);
$quizStmt->bind_param("i", $quizId);
$quizStmt->execute();
$quiz = $quizStmt->get_result()->fetch_assoc();

// Fetch questions for this quiz
$questionsSql = "SELECT q.*, 
                 CASE WHEN q.question_type = 'fill_blank' 
                      THEN (SELECT GROUP_CONCAT(correct_answer SEPARATOR '||') 
                            FROM fill_blank_answers 
                            WHERE question_id = q.question_id)
                      ELSE NULL
                 END AS fill_blank_answers
                 FROM questions q 
                 WHERE q.quiz_id = ? 
                 ORDER BY q.question_id";
$questionsStmt = $connect->prepare($questionsSql);
$questionsStmt->bind_param("i", $quizId);
$questionsStmt->execute();
$questions = $questionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quiz - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fa;
        }
        .quiz-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        .quiz-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }
        .quiz-title {
            color: #333;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .quiz-meta {
            color: #666;
            font-size: 1rem;
        }
        .question-container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .question-container:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .question-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }
        .options-list {
            list-style-type: none;
            padding-left: 0;
        }
        .option-item {
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        .option-item:hover {
            background-color: #e9ecef;
        }
        .correct-answer {
            background-color: #d4edda;
            color: #155724;
            font-weight: 600;
        }
        .question-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
        .marks {
            font-weight: 600;
        }
        .fill-blank-answer {
            background-color: #e7f3fe;
            color: #1a73e8;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="quiz-container">
                <div class="quiz-header">
                    <h1 class="quiz-title"><?php echo htmlspecialchars($quiz['quiz_name']); ?></h1>
                    <p class="quiz-meta">
                        Date: <?php echo date('F d, Y', strtotime($quiz['creation_date'])); ?> | 
                        Duration: <?php echo $quiz['duration_minutes']; ?> minutes
                    </p>
                </div>

                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-container">
                        <p class="question-text">Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($question['question_text']); ?></p>
                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                            <ul class="options-list">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <li class="option-item <?php echo $i == $question['correct_option'] ? 'correct-answer' : ''; ?>">
                                        <?php echo htmlspecialchars($question["option{$i}"]); ?>
                                        <?php if ($i == $question['correct_option']) echo " ✓"; ?>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                            <div class="question-footer">
                                <span class="marks">Marks: <?php echo $question['marks']; ?></span>
                                <span>Correct Answer: Option <?php echo $question['correct_option']; ?></span>
                            </div>
                        <?php elseif ($question['question_type'] === 'fill_blank'): ?>
                            <div class="fill-blank-answer">
                                Correct Answer(s): 
                                <?php 
                                $answers = explode('||', $question['fill_blank_answers']);
                                echo implode(', ', array_map('htmlspecialchars', $answers));
                                ?>
                            </div>
                            <div class="question-footer">
                                <span class="marks">Marks: <?php echo $question['marks']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
  
        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
</body>
</html>