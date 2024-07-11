<?php
include "include/session.php";
include "include/connect.php";

// Check if trainer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to trainers list if no ID is provided
    header("Location: view-trainers.php");
    exit();
}

$trainerId = $_GET['id'];

// Fetch trainer details
$stmt = $connect->prepare("SELECT * FROM trainers WHERE id = ?");
$stmt->bind_param("i", $trainerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect if trainer not found
    header("Location: view-trainers.php");
    exit();
}

$trainer = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Trainer - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Edit Trainer</h2>
          
          <form id="editTrainerForm" enctype="multipart/form-data">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
            <input value="<?php echo $trainerId ?>" hidden name="id">

            <div class="mb-3">
              <label for="name" class="form-label">Name</label>
              <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($trainer['name']); ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="designation" class="form-label">Designation</label>
              <input type="text" class="form-control" id="designation" name="designation" value="<?php echo htmlspecialchars($trainer['designation']); ?>" required>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($trainer['description']); ?></textarea>
            </div>

            <div class="mb-3">
              <label for="short_description" class="form-label">Short Description</label>
              <textarea class="form-control" id="short_description" name="short_description" rows="2" required><?php echo htmlspecialchars($trainer['short_description']); ?></textarea>
            </div>

            <div class="mb-3">
              <label for="image" class="form-label">Image</label>
              <input type="file" class="form-control" id="image" name="image" accept="image/*">
              <?php if (!empty($trainer['image'])): ?>
                <img src="<?php echo $uri.$trainer['image']; ?>" alt="Current Image" class="mt-2" style="max-width: 150px; border-radius: 50%; border: 5px solid green;">
              <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Update Trainer</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
  $(document).ready(function() {
    $('#editTrainerForm').on('submit', function(e) {
      e.preventDefault();
      
      var formData = new FormData(this);
      
      $.ajax({
        url: '<?php echo $apiEndpoint; ?>insertTrainer',
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
              // Redirect to trainers list or refresh the page
              window.location.href = 'trainers.php';
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
            text: 'An error occurred while updating the trainer.'
          });
        }
      });
    });
  });
  </script>
</body>
</html>