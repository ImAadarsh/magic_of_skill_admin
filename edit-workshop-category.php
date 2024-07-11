<?php
include "include/session.php";
include "include/connect.php";

// Get the category ID from the URL parameter
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the category data
$categoryData = null;
if ($categoryId > 0) {
    $query = "SELECT * FROM categories WHERE id = $categoryId";
    $result = mysqli_query($connect, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $categoryData = mysqli_fetch_assoc($result);
    }
}

// If category not found, redirect to the category list page
if (!$categoryData) {
    header("Location: workshop_categories.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Workshop Category - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Edit Workshop Category</h2>
          
          <form id="editCategoryForm">
            <input type="hidden" name="id" value="<?php echo $categoryData['id']; ?>">
            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">

            <div class="mb-3">
              <label for="name" class="form-label">Category Name</label>
              <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($categoryData['name']); ?>" required>
            </div>

            <div class="mb-3">
              <label for="sequence" class="form-label">Sequence</label>
              <input type="number" class="form-control" id="sequence" name="sequence" value="<?php echo $categoryData['sequence']; ?>" required>
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
      url: '<?php echo $apiEndpoint; ?>insertCategory',
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
            // Redirect to the category list page
            window.location.href = 'workshop-categories.php';
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