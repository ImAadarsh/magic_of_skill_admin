<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'include/session.php';
include 'include/connect.php';

$quizId = $_GET['id'] ?? 0;

// Handle existing question updates
if (isset($_POST['questions']) && is_array($_POST['questions'])) {
    foreach ($_POST['questions'] as $questionId => $question) {
        $questionText = mysqli_real_escape_string($connect, $question['text']);
        $questionType = $question['type'];
        $marks = floatval($question['marks']);

        // Common update for both question types
        $updateQuestionSql = "UPDATE questions SET question_text = ?, question_type = ?, marks = ? WHERE question_id = ?";
        $updateQuestionStmt = $connect->prepare($updateQuestionSql);
        
        if ($updateQuestionStmt === false) {
            die("Prepare failed: " . $connect->error . " for query: " . $updateQuestionSql);
        }

        $bindResult = $updateQuestionStmt->bind_param("ssdi", $questionText, $questionType, $marks, $questionId);
        if ($bindResult === false) {
            die("Binding parameters failed: " . $updateQuestionStmt->error);
        }

        $executeResult = $updateQuestionStmt->execute();
        if ($executeResult === false) {
            die("Execute failed: " . $updateQuestionStmt->error);
        }

        // echo "Question $questionId basic info updated successfully.<br>";

        // For multiple choice questions
        if ($questionType === 'multiple_choice') {
            $updateOptionsSql = "UPDATE questions SET option1 = ?, option2 = ?, option3 = ?, option4 = ?, correct_option = ? WHERE question_id = ?";
            $updateOptionsStmt = $connect->prepare($updateOptionsSql);
            
            if ($updateOptionsStmt === false) {
                die("Prepare failed: " . $connect->error . " for query: " . $updateOptionsSql);
            }

            $option1 = mysqli_real_escape_string($connect, $question['option1']);
            $option2 = mysqli_real_escape_string($connect, $question['option2']);
            $option3 = mysqli_real_escape_string($connect, $question['option3']);
            $option4 = mysqli_real_escape_string($connect, $question['option4']);
            $correctOption = intval($question['correct_option']);

            $bindResult = $updateOptionsStmt->bind_param("ssssii", $option1, $option2, $option3, $option4, $correctOption, $questionId);
            if ($bindResult === false) {
                die("Binding parameters failed: " . $updateOptionsStmt->error);
            }

            $executeResult = $updateOptionsStmt->execute();
            if ($executeResult === false) {
                die("Execute failed: " . $updateOptionsStmt->error);
            }

            // echo "Multiple choice options for question $questionId updated successfully.<br>";
        } 
        // For fill in the blank questions
        elseif ($questionType === 'fill_blank') {
            // Delete existing answers
            $deleteAnswersSql = "DELETE FROM fill_blank_answers WHERE question_id = ?";
            $deleteAnswersStmt = $connect->prepare($deleteAnswersSql);
            $deleteAnswersStmt->bind_param("i", $questionId);
            $deleteAnswersStmt->execute();

            // Insert new answers
            $insertAnswerSql = "INSERT INTO fill_blank_answers (question_id, correct_answer) VALUES (?, ?)";
            $insertAnswerStmt = $connect->prepare($insertAnswerSql);
            
            if ($insertAnswerStmt === false) {
                die("Prepare failed: " . $connect->error . " for query: " . $insertAnswerSql);
            }

            foreach ($question['correct_answers'] as $answer) {
                $correctAnswer = mysqli_real_escape_string($connect, $answer);
                $bindResult = $insertAnswerStmt->bind_param("is", $questionId, $correctAnswer);
                if ($bindResult === false) {
                    die("Binding parameters failed: " . $insertAnswerStmt->error);
                }

                $executeResult = $insertAnswerStmt->execute();
                if ($executeResult === false) {
                    die("Execute failed: " . $insertAnswerStmt->error);
                }
            }

            // echo "Fill-in-the-blank answers for question $questionId updated successfully.<br>";
        }
    }
}
    // ... (code for handling new questions remains the same)

    // Redirect back to

$quizId = $_GET['id'] ?? 0;


// Fetch quiz details
$quizSql = "SELECT * FROM quizzes WHERE quiz_id = ?";


$quizStmt = $connect->prepare($quizSql);

if ($quizStmt === false) {
    die("Prepare failed: " . $connect->error . " (Error number: " . $connect->errno . ")");
}



if (!$quizStmt->bind_param("i", $quizId)) {
    die("Binding parameters failed: " . $quizStmt->error);
}


if (!$quizStmt->execute()) {
    die("Execute failed: " . $quizStmt->error);
}



$result = $quizStmt->get_result();
if ($result === false) {
    die("Getting result set failed: " . $quizStmt->error);
}

$quiz = $result->fetch_assoc();

if ($quiz === null) {
    die("No quiz found with ID: " . $quizId);
}

// Fetch questions for this quiz
// echo "Fetching questions for quiz ID: " . $quizId . "<br>";

$questionsSql = "SELECT q.*, 
                        CASE 
                            WHEN q.question_type = 'multiple_choice' THEN 
                                CONCAT_WS('||', q.option1, q.option2, q.option3, q.option4)
                            ELSE 
                                (SELECT GROUP_CONCAT(correct_answer SEPARATOR '||') 
                                 FROM fill_blank_answers 
                                 WHERE question_id = q.question_id)
                        END AS options_or_answer
                 FROM questions q 
                 WHERE q.quiz_id = ?";

// echo "Questions SQL: " . $questionsSql . "<br>";

$questionsStmt = $connect->prepare($questionsSql);

if ($questionsStmt === false) {
    die("Prepare failed for questions query: " . $connect->error . " (Error number: " . $connect->errno . ")");
}

// echo "Questions prepare statement successful.<br>";

if (!$questionsStmt->bind_param("i", $quizId)) {
    die("Binding parameters failed for questions query: " . $questionsStmt->error);
}

// echo "Questions parameter binding successful.<br>";

if (!$questionsStmt->execute()) {
    die("Execute failed for questions query: " . $questionsStmt->error);
}

// echo "Questions query executed successfully.<br>";

$questionsResult = $questionsStmt->get_result();
if ($questionsResult === false) {
    die("Getting result set failed for questions query: " . $questionsStmt->error);
}

$questions = $questionsResult->fetch_all(MYSQLI_ASSOC);

// echo "Questions fetched successfully. Number of questions: " . count($questions) . "<br>";

// Process the fetched questions
foreach ($questions as &$question) {
    if ($question['question_type'] === 'fill_blank') {
        // Split multiple correct answers if any
        $question['correct_answers'] = explode('||', $question['options_or_answer']);
    } else {
        $question['options'] = explode('||', $question['options_or_answer']);
    }
    unset($question['options_or_answer']);
}

?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <style>
        .question-block {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <h2>Edit Quiz</h2>
            <form method="POST">
                <div class="mb-3">
                    <label for="quiz_name" class="form-label">Quiz Name</label>
                    <input type="text" class="form-control" id="quiz_name" name="quiz_name" value="<?php echo htmlspecialchars($quiz['quiz_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="quiz_date" class="form-label">Quiz Date</label>
                    <input type="date" class="form-control" id="quiz_date" name="quiz_date" value="<?php echo $quiz['creation_date']; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                    <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" value="<?php echo $quiz['duration_minutes']; ?>" required>
                </div>

                <h3>Existing Questions</h3>
                <div id="existing-questions">
                <?php foreach ($questions as $index => $question): ?>
    <div class="question-block mb-4">
        <h4>Question <?php echo $index + 1; ?></h4>
        <input type="hidden" name="questions[<?php echo $question['question_id']; ?>][id]" value="<?php echo $question['question_id']; ?>">
        <input type="hidden" name="questions[<?php echo $question['question_id']; ?>][type]" value="<?php echo $question['question_type']; ?>">
        <div class="mb-3">
            <label class="form-label">Question Text</label>
            <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][text]" value="<?php echo htmlspecialchars($question['question_text']); ?>" required>
        </div>
        <?php if ($question['question_type'] === 'multiple_choice'): ?>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="mb-3">
                    <label class="form-label">Option <?php echo $i; ?></label>
                    <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][option<?php echo $i; ?>]" value="<?php echo htmlspecialchars($question['option'.$i]); ?>" required>
                </div>
            <?php endfor; ?>
            <div class="mb-3">
                <label class="form-label">Correct Option</label>
                <select class="form-control" name="questions[<?php echo $question['question_id']; ?>][correct_option]" required>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $question['correct_option'] == $i ? 'selected' : ''; ?>>Option <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <label class="form-label">Correct Answer(s)</label>
                <?php foreach ($question['correct_answers'] as $index => $answer): ?>
                    <input type="text" class="form-control mb-2" name="questions[<?php echo $question['question_id']; ?>][correct_answers][]" value="<?php echo htmlspecialchars($answer); ?>" required>
                <?php endforeach; ?>
                <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addCorrectAnswer(this, <?php echo $question['question_id']; ?>)">Add Another Correct Answer</button>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Marks</label>
            <input type="number" class="form-control" name="questions[<?php echo $question['question_id']; ?>][marks]" value="<?php echo $question['marks']; ?>" required>
        </div>
    </div>
<?php endforeach; ?>
                </div>

                <h3>Add New Questions</h3>
                <div id="new-questions-container"></div>
                <div class="mb-3">
                    <button type="button" class="btn btn-secondary" onclick="addQuestion('multiple_choice')">Add Multiple Choice Question</button>
                    <button type="button" class="btn btn-secondary" onclick="addQuestion('fill_blank')">Add Fill in the Blank Question</button>
                </div>

                <button type="submit" class="btn btn-primary">Update Quiz</button>
            </form>
        </div>
  
        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
    <script>
        let newQuestionCount = 0;

        function addQuestion(type) {
            newQuestionCount++;
            let questionHtml = `
                <div class="question-block mb-4">
                    <h4>New Question ${newQuestionCount}</h4>
                    <input type="hidden" name="new_questions[${newQuestionCount}][type]" value="${type}">
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][text]" required>
                    </div>
            `;

            if (type === 'multiple_choice') {
                questionHtml += `
                    <div class="mb-3">
                        <label class="form-label">Options:</label>
                        <input type="text" class="form-control mb-2" name="new_questions[${newQuestionCount}][options][]" placeholder="Option 1" required>
                        <input type="text" class="form-control mb-2" name="new_questions[${newQuestionCount}][options][]" placeholder="Option 2" required>
                        <input type="text" class="form-control mb-2" name="new_questions[${newQuestionCount}][options][]" placeholder="Option 3" required>
                        <input type="text" class="form-control mb-2" name="new_questions[${newQuestionCount}][options][]" placeholder="Option 4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correct Option:</label>
                        <select class="form-control" name="new_questions[${newQuestionCount}][correct_option]">
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3">Option 3</option>
                            <option value="4">Option 4</option>
                        </select>
                    </div>
                `;
            } else if (type === 'fill_blank') {
                questionHtml += `
                    <div class="mb-3">
                        <label class="form-label">Correct Answer:</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][correct_answer]" required>
                    </div>
                `;
            }

            questionHtml += `
                    <div class="mb-3">
                        <label class="form-label">Marks</label>
                        <input type="number" class="form-control" name="new_questions[${newQuestionCount}][marks]" required>
                    </div>
                </div>
            `;

            $('#new-questions-container').append(questionHtml);
        }

        function addCorrectAnswer(button, questionId) {
    const container = button.closest('.mb-3');
    const newInput = document.createElement('input');
    newInput.type = 'text';
    newInput.className = 'form-control mb-2';
    newInput.name = `questions[${questionId}][correct_answers][]`;
    newInput.required = true;
    container.insertBefore(newInput, button);
}


    </script>
</body>
</html>