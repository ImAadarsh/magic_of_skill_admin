<?php
include "include/session.php";
include "include/connect.php";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Quiz - Magic Of Skills Dashboard</title>
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
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Add New Quiz</h2>
          
          <form id="addQuizForm">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
            
            <div class="mb-3">
              <label for="quiz_name" class="form-label">Quiz Name</label>
              <input type="text" class="form-control" id="quiz_name" name="quiz_name" required>
            </div>
            
            <div class="mb-3">
              <label for="quiz_description" class="form-label">Description</label>
              <textarea class="form-control" id="quiz_description" name="quiz_description" rows="3" required></textarea>
            </div>

            <div class="mb-3">
              <label for="quiz_date" class="form-label">Quiz Date</label>
              <input type="date" class="form-control" id="quiz_date" name="quiz_date" required>
            </div>

            <div class="mb-3">
              <label for="duration_minutes" class="form-label">Duration (in minutes)</label>
              <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" required>
            </div>

            <div id="questions-container">
              <!-- Questions will be added here dynamically -->
            </div>

            <button type="button" class="btn btn-secondary mb-3" id="add-question">Add Question</button>

            <button type="submit" class="btn btn-primary">Submit Quiz</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
    let questionCount = 0;

    function addQuestion() {
      questionCount++;
      const questionHtml = `
        <div class="question-block" id="question-${questionCount}">
          <h4>Question ${questionCount}</h4>
          <div class="mb-3">
            <label for="question_text_${questionCount}" class="form-label">Question Text</label>
            <input type="text" class="form-control" id="question_text_${questionCount}" name="questions[${questionCount}][text]" required>
          </div>
          <div class="mb-3">
            <label for="question_marks_${questionCount}" class="form-label">Marks</label>
            <input type="number" class="form-control" id="question_marks_${questionCount}" name="questions[${questionCount}][marks]" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Options</label>
            <div class="input-group mb-2">
              <div class="input-group-text">
                <input class="form-check-input mt-0" type="radio" name="questions[${questionCount}][correct_option]" value="1" required>
              </div>
              <input type="text" class="form-control" name="questions[${questionCount}][options][]" placeholder="Option 1" required>
            </div>
            <div class="input-group mb-2">
              <div class="input-group-text">
                <input class="form-check-input mt-0" type="radio" name="questions[${questionCount}][correct_option]" value="2">
              </div>
              <input type="text" class="form-control" name="questions[${questionCount}][options][]" placeholder="Option 2" required>
            </div>
            <div class="input-group mb-2">
              <div class="input-group-text">
                <input class="form-check-input mt-0" type="radio" name="questions[${questionCount}][correct_option]" value="3">
              </div>
              <input type="text" class="form-control" name="questions[${questionCount}][options][]" placeholder="Option 3" required>
            </div>
            <div class="input-group mb-2">
              <div class="input-group-text">
                <input class="form-check-input mt-0" type="radio" name="questions[${questionCount}][correct_option]" value="4">
              </div>
              <input type="text" class="form-control" name="questions[${questionCount}][options][]" placeholder="Option 4" required>
            </div>
          </div>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(${questionCount})">Remove Question</button>
        </div>
      `;
      $('#questions-container').append(questionHtml);
    }

    function removeQuestion(id) {
      $(`#question-${id}`).remove();
    }

    $('#add-question').click(addQuestion);

    $('#addQuizForm').on('submit', function(e) {
  e.preventDefault();
  
  var formData = new FormData(this);
  
  $.ajax({
    url: 'controller/insertQuiz.php',
    type: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    dataType: 'json', // Add this line to ensure jQuery parses the JSON response
    success: function(response) {
      console.log('Response received:', response);
      
      // Parse the response if it's a string
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response);
        } catch (e) {
          console.error('Error parsing JSON:', e);
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Error processing server response'
          });
          return;
        }
      }
      
      if(response.status === true) { // Use strict comparison
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: response.message,
          showConfirmButton: false,
          timer: 1500
        }).then(() => {
          // Reset form
          $('#addQuizForm')[0].reset();
          $('#questions-container').empty();
          questionCount = 0;
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Oops...',
          text: response.message || 'Something went wrong!'
        });
      }
    },
    error: function(xhr, status, error) {
      console.error('AJAX error:', status, error);
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: 'An error occurred while submitting the form.'
      });
    }
  });
});
  </script>
</body>
</html>