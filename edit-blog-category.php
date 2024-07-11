<?php
include "include/session.php";
include "include/connect.php";

// Fetch category data
$category_id = $_GET['id']; // Assuming the category ID is passed in the URL
$sql = "SELECT * FROM blog_categories WHERE id = $category_id";
$result = $connect->query($sql);
$category = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Blog Category - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Edit Blog Category</h2>
          
          <form id="editCategoryForm">
            <div class="mb-3">
              <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
              <input value="<?php echo $category_id ?>" hidden name="id">
              <label for="name" class="form-label">Category Name</label>
              <input type="text" class="form-control" id="name" name="name" value="<?php echo $category['name']; ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Update</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
  $('#editCategoryForm').on('submit', function(e) {
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
            // Redirect to category list or refresh page
            window.location.href = 'blog-categories.php';
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
          text: 'An error occurred while updating the category.'
        });
      }
    });
  });
  </script>
</body>
</html>