<?php
include "include/session.php";
include "include/connect.php";

// Check if workshop ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to workshops list if no ID is provided
    header("Location: workshops.php");
    exit();
}

$workshopId = $_GET['id'];

// Fetch workshop details
$sql = "SELECT * FROM workshops WHERE id = ?";
$stmt = $connect->prepare($sql);
$stmt->bind_param("i", $workshopId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect to workshops list if workshop not found
    header("Location: workshops.php");
    exit();
}

$workshop = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Workshop - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Edit Workshop</h2>
          
          <form id="editWorkshopForm" enctype="multipart/form-data">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
            <input value="<?php echo $workshopId ?>" hidden name="id">
            
            <div class="mb-3">
              <label for="name" class="form-label">Workshop Name</label>
              <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($workshop['name']); ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($workshop['description']); ?></textarea>
            </div>

            <div class="mb-3">
              <label for="trainer" class="form-label">Trainer</label>
              <select class="form-select" id="trainer" name="trainer_id" required>
                <?php
                $sql = "SELECT * FROM trainers";
                $results = $connect->query($sql);
                while($final = $results->fetch_assoc()){ ?>
                  <option value="<?php echo $final['id'];?>" <?php echo ($final['id'] == $workshop['trainer_id']) ? 'selected' : ''; ?>><?php echo $final['name'];?></option>
                <?php } ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="category" class="form-label">Category</label>
              <select class="form-select" id="category" name="category_id" required>
                <?php
                $sql = "SELECT * FROM categories";
                $results = $connect->query($sql);
                while($final = $results->fetch_assoc()){ ?>
                  <option value="<?php echo $final['id'];?>" <?php echo ($final['id'] == $workshop['category_id']) ? 'selected' : ''; ?>><?php echo $final['name'];?></option>
                <?php } ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="level" class="form-label">Level</label>
              <select class="form-select" id="level" name="level" required>
                <option value="1" <?php echo ($workshop['level'] == 1) ? 'selected' : ''; ?>>Beginner</option>
                <option value="2" <?php echo ($workshop['level'] == 2) ? 'selected' : ''; ?>>Intermediate</option>
                <option value="3" <?php echo ($workshop['level'] == 3) ? 'selected' : ''; ?>>Advanced</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="learnings" class="form-label">Learnings</label>
              <textarea class="form-control" id="learnings" name="learnings" rows="3" required><?php echo htmlspecialchars($workshop['learnings']); ?></textarea>
              <small class="form-text text-muted">Use &lt;br&gt; to separate learning points</small>
            </div>

            <div class="mb-3">
              <label for="short_description" class="form-label">Short Description</label>
              <textarea class="form-control" id="short_description" name="short_description" rows="2" required><?php echo htmlspecialchars($workshop['short_description']); ?></textarea>
            </div>

            <div class="mb-3">
              <label for="start_time" class="form-label">Start Time</label>
              <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?php echo date('Y-m-d\TH:i', strtotime($workshop['start_time'])); ?>" required>
            </div>

            <div class="mb-3">
              <label for="duration" class="form-label">Duration (in minutes)</label>
              <input type="number" class="form-control" id="duration" name="duration" value="<?php echo $workshop['duration']; ?>" required>
            </div>

            <div class="mb-3">
              <label for="cut_price" class="form-label">Cut Price</label>
              <input type="number" step="0.01" class="form-control" id="cut_price" name="cut_price" value="<?php echo $workshop['cut_price']; ?>" required>
            </div>

            <div class="mb-3">
              <label for="price" class="form-label">Price</label>
              <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo $workshop['price']; ?>" required>
            </div>

            <div class="mb-3">
              <label for="recording" class="form-label">Recording Link</label>
              <input type="url" class="form-control" id="recording" name="recording" value="<?php echo htmlspecialchars($workshop['recording']); ?>">
            </div>

            <div class="mb-3">
              <label for="workshop_link" class="form-label">Workshop Link</label>
              <input type="url" class="form-control" id="workshop_link" name="workshop_link" value="<?php echo htmlspecialchars($workshop['workshop_link']); ?>">
            </div>

            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_free" name="is_free" value="1" <?php echo ($workshop['is_free'] == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_free">Is Free Workshop?</label>
              </div>
            </div>

            <div class="mb-3">
              <label for="icon" class="form-label">Icon</label>
              <input type="file" class="form-control" id="icon" name="icon" accept="image/*">
              <img id="iconPreview" src="<?php echo $uri . $workshop['icon']; ?>" alt="Icon preview" style="max-width: 100px; margin-top: 10px;">
            </div>

            <div class="mb-3">
              <label for="banner_image" class="form-label">Banner Image</label>
              <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*">
              <img id="bannerPreview" src="<?php echo $uri . $workshop['banner_image']; ?>" alt="Banner preview" style="max-width: 200px; margin-top: 10px;">
            </div>

            <button type="submit" class="btn btn-primary">Update Workshop</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
    // Image preview functionality
    function readURL(input, previewId) {
      if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
          $(previewId).attr('src', e.target.result);
          $(previewId).show();
        }
        
        reader.readAsDataURL(input.files[0]);
      }
    }

    $("#icon").change(function() {
      readURL(this, "#iconPreview");
    });

    $("#banner_image").change(function() {
      readURL(this, "#bannerPreview");
    });

    $('#editWorkshopForm').on('submit', function(e) {
      e.preventDefault();
      
      var formData = new FormData(this);
      
      $.ajax({
        url: '<?php echo $apiEndpoint; ?>insertWorkshop',
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
              // Redirect to workshops list or refresh the page
              window.location.href = 'workshops.php';
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