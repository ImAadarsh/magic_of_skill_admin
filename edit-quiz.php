<?php
include 'include/session.php';
include 'include/connect.php';

$quizId = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    $quizName = mysqli_real_escape_string($connect, $_POST['quiz_name']);
    $quizDate = mysqli_real_escape_string($connect, $_POST['quiz_date']);
    $durationMinutes = intval($_POST['duration_minutes']);

    // Update quiz details
    $updateQuizSql = "UPDATE quizzes SET quiz_name = ?, creation_date = ?, duration_minutes = ? WHERE quiz_id = ?";
    $updateQuizStmt = $connect->prepare($updateQuizSql);
    $updateQuizStmt->bind_param("ssii", $quizName, $quizDate, $durationMinutes, $quizId);
    $updateQuizStmt->execute();

    // Handle existing question updates
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $questionId => $question) {
            $questionText = mysqli_real_escape_string($connect, $question['text']);
            $option1 = mysqli_real_escape_string($connect, $question['option1']);
            $option2 = mysqli_real_escape_string($connect, $question['option2']);
            $option3 = mysqli_real_escape_string($connect, $question['option3']);
            $option4 = mysqli_real_escape_string($connect, $question['option4']);
            $correctOption = intval($question['correct_option']);
            $marks = floatval($question['marks']);

            $updateQuestionSql = "UPDATE questions SET question_text = ?, option1 = ?, option2 = ?, option3 = ?, option4 = ?, correct_option = ?, marks = ? WHERE question_id = ?";
            $updateQuestionStmt = $connect->prepare($updateQuestionSql);
            $updateQuestionStmt->bind_param("sssssiid", $questionText, $option1, $option2, $option3, $option4, $correctOption, $marks, $questionId);
            $updateQuestionStmt->execute();
        }
    }

    // Handle new questions
    if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
        $insertQuestionSql = "INSERT INTO questions (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertQuestionStmt = $connect->prepare($insertQuestionSql);

        foreach ($_POST['new_questions'] as $newQuestion) {
            $questionText = mysqli_real_escape_string($connect, $newQuestion['text']);
            $option1 = mysqli_real_escape_string($connect, $newQuestion['option1']);
            $option2 = mysqli_real_escape_string($connect, $newQuestion['option2']);
            $option3 = mysqli_real_escape_string($connect, $newQuestion['option3']);
            $option4 = mysqli_real_escape_string($connect, $newQuestion['option4']);
            $correctOption = intval($newQuestion['correct_option']);
            $marks = floatval($newQuestion['marks']);

            $insertQuestionStmt->bind_param("isssssid", $quizId, $questionText, $option1, $option2, $option3, $option4, $correctOption, $marks);
            $insertQuestionStmt->execute();
        }
    }

    // Redirect back to the quiz list or show a success message
    header("Location: quizzes.php?message=Quiz updated successfully");
    exit();
}

// Fetch quiz details
$quizSql = "SELECT * FROM quizzes WHERE quiz_id = ?";
$quizStmt = $connect->prepare($quizSql);
$quizStmt->bind_param("i", $quizId);
$quizStmt->execute();
$quiz = $quizStmt->get_result()->fetch_assoc();

// Fetch questions for this quiz
$questionsSql = "SELECT * FROM questions WHERE quiz_id = ?";
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
                            <div class="mb-3">
                                <label class="form-label">Question Text</label>
                                <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][text]" value="<?php echo htmlspecialchars($question['question_text']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Option 1</label>
                                <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][option1]" value="<?php echo htmlspecialchars($question['option1']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Option 2</label>
                                <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][option2]" value="<?php echo htmlspecialchars($question['option2']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Option 3</label>
                                <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][option3]" value="<?php echo htmlspecialchars($question['option3']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Option 4</label>
                                <input type="text" class="form-control" name="questions[<?php echo $question['question_id']; ?>][option4]" value="<?php echo htmlspecialchars($question['option4']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correct Option</label>
                                <select class="form-control" name="questions[<?php echo $question['question_id']; ?>][correct_option]" required>
                                    <option value="1" <?php echo $question['correct_option'] == 1 ? 'selected' : ''; ?>>Option 1</option>
                                    <option value="2" <?php echo $question['correct_option'] == 2 ? 'selected' : ''; ?>>Option 2</option>
                                    <option value="3" <?php echo $question['correct_option'] == 3 ? 'selected' : ''; ?>>Option 3</option>
                                    <option value="4" <?php echo $question['correct_option'] == 4 ? 'selected' : ''; ?>>Option 4</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Marks</label>
                                <input type="number" class="form-control" name="questions[<?php echo $question['question_id']; ?>][marks]" value="<?php echo $question['marks']; ?>" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3>Add New Questions</h3>
                <div id="new-questions-container"></div>
                <button type="button" class="btn btn-secondary mb-3" id="add-question">Add New Question</button>

                <button type="submit" class="btn btn-primary">Update Quiz</button>
            </form>
        </div>
  
        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
    <script>
        let newQuestionCount = 0;

        function addNewQuestion() {
            newQuestionCount++;
            const questionHtml = `
                <div class="question-block mb-4">
                    <h4>New Question ${newQuestionCount}</h4>
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][text]" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Option 1</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][option1]" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Option 2</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][option2]" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Option 3</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][option3]" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Option 4</label>
                        <input type="text" class="form-control" name="new_questions[${newQuestionCount}][option4]" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correct Option</label>
                        <select class="form-control" name="new_questions[${newQuestionCount}][correct_option]" required>
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3">Option 3</option>
                            <option value="4">Option 4</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Marks</label>
                        <input type="number" class="form-control" name="new_questions[${newQuestionCount}][marks]" required>
                    </div>
                </div>
            `;
            $('#new-questions-container').append(questionHtml);
        }

        $('#add-question').click(addNewQuestion);
    </script>
</body>
</html>