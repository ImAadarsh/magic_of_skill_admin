<?php
include "include/session.php";
include "include/connect.php";

// Check if coupon ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to coupons list if no ID is provided
    header("Location: view-coupons.php");
    exit();
}

$couponId = $_GET['id'];

// Fetch coupon details
$stmt = $connect->prepare("SELECT * FROM coupons WHERE id = ?");
$stmt->bind_param("i", $couponId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect if coupon not found
    header("Location: view-coupons.php");
    exit();
}

$coupon = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Coupon - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Edit Coupon</h2>
          
          <form id="editCouponForm">
            <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
            <input value="<?php echo $couponId ?>" hidden name="id">

            <div class="mb-3">
              <label for="coupon_code" class="form-label">Coupon Code</label>
              <input type="text" class="form-control" id="coupon_code" name="coupon_code" maxlength="15" value="<?php echo htmlspecialchars($coupon['coupon_code']); ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="discount_type" class="form-label">Discount Type</label>
              <select class="form-select" id="discount_type" name="discount_type" required>
                <option value="1" <?php echo $coupon['discount_type'] == 1 ? 'selected' : ''; ?>>Percentage</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="value" class="form-label">Value</label>
              <input type="number" step="0.01" class="form-control" id="value" name="value" value="<?php echo $coupon['value']; ?>" required>
            </div>

            <div class="mb-3">
              <label for="valid_till" class="form-label">Valid Till</label>
              <input type="datetime-local" class="form-control" id="valid_till" name="valid_till" value="<?php echo date('Y-m-d\TH:i', strtotime($coupon['valid_till'])); ?>" required>
            </div>

            <div class="mb-3">
              <label for="count" class="form-label">Count</label>
              <input type="number" class="form-control" id="count" name="count" value="<?php echo $coupon['count']; ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Update Coupon</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>

  <script>
  $(document).ready(function() {
    $('#editCouponForm').on('submit', function(e) {
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
              text: 'Coupon updated successfully',
              showConfirmButton: false,
              timer: 1500
            }).then(() => {
              // Redirect to coupons list or refresh the page
              window.location.href = 'coupons.php';
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
            text: 'An error occurred while updating the coupon.'
          });
        }
      });
    });
  });
  </script>
</body>
</html>