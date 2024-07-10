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
  <h6 class="fw-semibold mb-0">Line Chart</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Components / Line Chart</li>
  </ul>
</div>

        <div class="row gy-4">
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Default Line Chart</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="defaultLineChart" class="apexcharts-tooltip-style-1"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Zoomable Chart</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="zoomAbleLineChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Line Chart with Data Labels</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="lineDataLabel"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Line Chart Animation</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="doubleLineChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Stepline Charts</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="stepLineChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 p-0">
                    <div class="card-header border-bottom bg-base py-16 px-24">
                        <h6 class="text-lg fw-semibold mb-0">Gradient Charts</h6>
                    </div>
                    <div class="card-body p-24">
                        <div id="gradientLineChart"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

<?php include "include/footer.php" ?>
</main>
<?php include "include/script.php" ?>

<script src="assets/js/lineChartPageChart.js"></script>

</body>
</html>
