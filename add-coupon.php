<?php
include "include/session.php";
include "include/connect.php";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Coupon - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Add New Coupon</h2>
          
          <form id="addCouponForm">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">

            <div class="mb-3">
              <label for="coupon_code" class="form-label">Coupon Code</label>
              <input type="text" class="form-control" id="coupon_code" name="coupon_code" maxlength="15" required>
            </div>
            
            <div class="mb-3">
              <label for="discount_type" class="form-label">Discount Type</label>
              <select class="form-select" id="discount_type" name="discount_type" required>
                <option value="1">Percentage</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="value" class="form-label">Value</label>
              <input type="number" step="0.01" class="form-control" id="value" name="value" required>
            </div>

            <div class="mb-3">
              <label for="valid_till" class="form-label">Valid Till</label>
              <input type="datetime-local" class="form-control" id="valid_till" name="valid_till" required>
            </div>

            <div class="mb-3">
              <label for="count" class="form-label">Count</label>
              <input type="number" class="form-control" id="count" name="count" required>
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
    $('#addCouponForm').on('submit', function(e) {
      e.preventDefault();
      
      var formData = new FormData(this);
      
      $.ajax({
        url: '<?php echo $apiEndpoint; ?>insertCoupon',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
          if(response.status) {
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: 'Coupon added successfully',
              showConfirmButton: false,
              timer: 1500
            }).then(() => {
              // Reset form
              $('#addCouponForm')[0].reset();
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