<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Magic Of Skills DashBoard</title>
<?php include "include/meta.php" ?>
</head>
  <body>
    <?php include "include/aside.php" ?>

<main class="dashboard-main">
    <?php include "include/header.php" ?>

  <div class="dashboard-main-body">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">404</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="index.php" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">404</li>
  </ul>
</div>
    
    <div class="card basic-data-table">
      <div class="card-body py-80 px-32 text-center">
        <img src="assets/images/error-img.png" alt="" class="mb-24">
        <h6 class="mb-16">Page not Found</h6>
        <p class="text-secondary-light">Sorry, the page you are looking for doesn’t exist </p>
        <a href="index.php" class="btn btn-primary-600 radius-8 px-20 py-11">Back to Home</a>
      </div>
    </div>
  </div>

  <footer class="d-footer">
  <div class="row align-items-center justify-content-between">
    <div class="col-auto">
      <p class="mb-0">© 2024 WowDash. All Rights Reserved.</p>
    </div>
    <div class="col-auto">
      <p class="mb-0">Made by <span class="text-primary-600">wowtheme7</span></p>
    </div>
  </div>
</footer>
</main>
<?php include "include/script.php" ?>
<script>
  let table = new DataTable('#dataTable');
</script>
</body>
</html>