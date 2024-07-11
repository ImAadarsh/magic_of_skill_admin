<?php
include "include/session.php";
include "include/connect.php";

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to events list if no ID is provided
    header("Location: view-events.php");
    exit();
}

$eventId = $_GET['id'];

// Fetch event details
$stmt = $connect->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect if event not found
    header("Location: view-events.php");
    exit();
}

$event = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Event - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Edit Event</h2>
          
          <form id="editEventForm" enctype="multipart/form-data">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
            <input value="<?php echo $eventId ?>" hidden name="id">

            <div class="mb-3">
              <label for="name" class="form-label">Event Name</label>
              <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($event['name']); ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($event['description']); ?></textarea>
            </div>

            <div class="mb-3">
              <label for="learning" class="form-label">Learning Outcomes</label>
              <textarea class="form-control" id="learning" name="learning" rows="3" required><?php echo htmlspecialchars($event['learning']); ?></textarea>
            </div>

            <div class="mb-3">
              <label for="location" class="form-label">Location</label>
              <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($event['location']); ?>" required>
            </div>

            <div class="mb-3">
              <label for="register_link" class="form-label">Registration Link</label>
              <input type="url" class="form-control" id="register_link" name="register_link" value="<?php echo htmlspecialchars($event['register_link']); ?>" required>
            </div>

            <div class="mb-3">
              <label for="date" class="form-label">Date</label>
              <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d', strtotime($event['date'])); ?>" required>
            </div>

            <div class="mb-3">
              <label for="time" class="form-label">Time</label>
              <input type="time" class="form-control" id="time" name="time" value="<?php echo date('H:i', strtotime($event['time'])); ?>" required>
            </div>

            <div class="mb-3">
              <label for="seat" class="form-label">Number of Seats</label>
              <input type="number" class="form-control" id="seat" name="seat" value="<?php echo $event['seat']; ?>" required>
            </div>

            <div class="mb-3">
              <label for="is_certificate" class="form-label">Certificate Provided</label>
              <select class="form-select" id="is_certificate" name="is_certificate" required>
                <option value="1" <?php echo $event['is_certificate'] == 1 ? 'selected' : ''; ?>>Yes</option>
                <option value="0" <?php echo $event['is_certificate'] == 0 ? 'selected' : ''; ?>>No</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="brand" class="form-label">Brand</label>
              <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($event['brand']); ?>" required>
            </div>

            <div class="mb-3">
              <label for="icon" class="form-label">Icon</label>
              <input type="file" class="form-control" id="icon" name="icon" accept="image/*">
              <?php if (!empty($event['icon'])): ?>
                <img src="<?php echo $uri.$event['icon']; ?>" alt="Current Icon" class="mt-2" style="max-width: 100px;">
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="banner_image" class="form-label">Banner Image</label>
              <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*">
              <?php if (!empty($event['banner_image'])): ?>
                <img src="<?php echo $uri.$event['banner_image']; ?>" alt="Current Banner" class="mt-2" style="max-width: 200px;">
              <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Update Event</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
  $(document).ready(function() {
    $('#editEventForm').on('submit', function(e) {
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
              // Redirect to events list or refresh the page
              window.location.href = 'events.php';
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
            text: 'An error occurred while updating the event.'
          });
        }
      });
    });
  });
  </script>
</body>
</html>