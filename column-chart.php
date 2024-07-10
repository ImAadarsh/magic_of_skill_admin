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
  <h6 class="fw-semibold mb-0">Column Chart</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="index.php" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Components / Column Chart</li>
  </ul>
</div>

        <div class="row gy-4">
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Column Charts</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="columnChart" class=""></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Column Charts</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="columnGroupBarChart" class=""></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Group Column</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="groupColumnBarChart" class=""></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Simple Column</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="upDownBarchart" class=""></div>
                    </div>
                </div>
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

<script src="assets/js/columnChartPageChart.js"></script>

</body>
</html>
