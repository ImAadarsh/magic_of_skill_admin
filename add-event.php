<?php
include "include/session.php";
include "include/connect.php";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Event - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Add New Event</h2>
          
          <form id="addEventForm" enctype="multipart/form-data">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">

            <div class="mb-3">
              <label for="name" class="form-label">Event Name</label>
              <input type="text" class="form-control" id="name" name="name" required>
            </div>
            
            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>

            <div class="mb-3">
              <label for="learning" class="form-label">Learning Outcomes</label>
              <textarea class="form-control" id="learning" name="learning" rows="3" required></textarea>
            </div>

            <div class="mb-3">
              <label for="location" class="form-label">Location</label>
              <input type="text" class="form-control" id="location" name="location" required>
            </div>

            <div class="mb-3">
              <label for="register_link" class="form-label">Registration Link</label>
              <input type="url" class="form-control" id="register_link" name="register_link" required>
            </div>

            <div class="mb-3">
              <label for="date" class="form-label">Date</label>
              <input type="date" class="form-control" id="date" name="date" required>
            </div>

            <div class="mb-3">
              <label for="time" class="form-label">Time</label>
              <input type="time" class="form-control" id="time" name="time" required>
            </div>

            <div class="mb-3">
              <label for="seat" class="form-label">Number of Seats</label>
              <input type="number" class="form-control" id="seat" name="seat" required>
            </div>

            <div class="mb-3">
              <label for="is_certificate" class="form-label">Certificate Provided</label>
              <select class="form-select" id="is_certificate" name="is_certificate" required>
                <option value="1">Yes</option>
                <option value="0">No</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="brand" class="form-label">Brand</label>
              <input type="text" class="form-control" id="brand" name="brand" required>
            </div>

            <div class="mb-3">
              <label for="icon" class="form-label">Icon</label>
              <input type="file" class="form-control" id="icon" name="icon" accept="image/*" required>
            </div>

            <div class="mb-3">
              <label for="banner_image" class="form-label">Banner Image</label>
              <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*" required>
            </div>

            <button type="submit" class="btn btn-primary">Submit</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
  $(document).ready(function() {
    $('#addEventForm').on('submit', function(e) {
      e.preventDefault();
      
      var formData = new FormData(this);
      
      $.ajax({
        url: '<?php echo $apiEndpoint; ?>insertEvent',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
          if(response.status) {
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: response.message,
              showConfirmButton: false,
              timer: 1500
            }).then(() => {
              // Reset form
              $('#addEventForm')[0].reset();
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
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'An error occurred while submitting the form.'
          });
        }
      });
    });
  });
  </script>
</body>
</html>