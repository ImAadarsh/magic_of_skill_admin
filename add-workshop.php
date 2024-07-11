<?php
include "include/session.php";
include "include/connect.php";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Workshop - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Add New Workshop</h2>
          
          <form id="addWorkshopForm" enctype="multipart/form-data">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
            
            <div class="mb-3">
              <label for="name" class="form-label">Workshop Name</label>
              <input type="text" class="form-control" id="name" name="name" required>
            </div>
            
            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>

            <div class="mb-3">
              <label for="trainer" class="form-label">Trainer</label>
              <select class="form-select" id="trainer" name="trainer_id" required>
                <?php
                $sql = "SELECT * FROM trainers";
                $results = $connect->query($sql);
                while($final = $results->fetch_assoc()){ ?>
                  <option value="<?php echo $final['id'];?>"><?php echo $final['name'];?></option>
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
                  <option value="<?php echo $final['id'];?>"><?php echo $final['name'];?></option>
                <?php } ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="level" class="form-label">Level</label>
              <select class="form-select" id="level" name="level" required>
                <option value="1">Beginner</option>
                <option value="2">Intermediate</option>
                <option value="3">Advanced</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="learnings" class="form-label">Learnings</label>
              <textarea class="form-control" id="learnings" name="learnings" rows="3" required></textarea>
              <small class="form-text text-muted">Use &lt;br&gt; to separate learning points</small>
            </div>

            <div class="mb-3">
              <label for="short_description" class="form-label">Short Description</label>
              <textarea class="form-control" id="short_description" name="short_description" rows="2" required></textarea>
            </div>

            <div class="mb-3">
              <label for="start_time" class="form-label">Start Time</label>
              <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
            </div>

            <div class="mb-3">
              <label for="duration" class="form-label">Duration (in minutes)</label>
              <input type="number" class="form-control" id="duration" name="duration" required>
            </div>

            <div class="mb-3">
              <label for="cut_price" class="form-label">Cut Price</label>
              <input type="number" step="0.01" class="form-control" id="cut_price" name="cut_price" required>
            </div>

            <div class="mb-3">
              <label for="price" class="form-label">Price</label>
              <input type="number" step="0.01" class="form-control" id="price" name="price" required>
            </div>

            <div class="mb-3">
              <label for="recording" class="form-label">Recording Link</label>
              <input type="url" class="form-control" id="recording" name="recording">
            </div>

            <div class="mb-3">
              <label for="workshop_link" class="form-label">Workshop Link</label>
              <input type="url" class="form-control" id="workshop_link" name="workshop_link">
            </div>

            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_free" name="is_free" value="1">
                <label class="form-check-label" for="is_free">Is Free Workshop?</label>
              </div>
            </div>

            <div class="mb-3">
              <label for="icon" class="form-label">Icon</label>
              <input type="file" class="form-control" id="icon" name="icon" accept="image/*" required>
              <img id="iconPreview" src="#" alt="Icon preview" style="display:none; max-width: 100px; margin-top: 10px;">
            </div>

            <div class="mb-3">
              <label for="banner_image" class="form-label">Banner Image</label>
              <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*" required>
              <img id="bannerPreview" src="#" alt="Banner preview" style="display:none; max-width: 200px; margin-top: 10px;">
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

    $('#addWorkshopForm').on('submit', function(e) {
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
              // Reset form
              $('#addWorkshopForm')[0].reset();
              $('#iconPreview').hide();
              $('#bannerPreview').hide();
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