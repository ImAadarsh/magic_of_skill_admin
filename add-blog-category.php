<?php
include "include/session.php";
include "include/connect.php";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Blog Category - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Add New Blog Category</h2>
          
          <form id="addCategoryForm">
            <div class="mb-3">
              <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
              <label for="name" class="form-label">Category Name</label>
              <input type="text" class="form-control" id="name" name="name" required>
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
  $('#addCategoryForm').on('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    
    $.ajax({
      url: '<?php echo $apiEndpoint; ?>insertBlogCategory',
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
            $('#addCategoryForm')[0].reset();
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
  </script>
</body>
</html>