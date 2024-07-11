<?php
include "include/connect.php";

// Fetch total users
$user_query = "SELECT COUNT(*) as total_users FROM users";
$user_result = mysqli_query($connect, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$total_users = $user_data['total_users'];

// Fetch active users (assuming users with recent activity)
$active_user_query = "SELECT COUNT(*) as active_users FROM users WHERE updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
$active_user_result = mysqli_query($connect, $active_user_query);
$active_user_data = mysqli_fetch_assoc($active_user_result);
$active_users = $active_user_data['active_users'];

// Fetch total sales
$sales_query = "SELECT SUM(amount) as total_sales FROM payments WHERE payment_status = 1";
$sales_result = mysqli_query($connect, $sales_query);
$sales_data = mysqli_fetch_assoc($sales_result);
$total_sales = $sales_data['total_sales'];

// Fetch conversion rate (assuming it's the ratio of completed payments to total users)
$conversion_query = "SELECT (COUNT(DISTINCT user_id) / (SELECT COUNT(*) FROM users)) * 100 as conversion_rate FROM payments WHERE payment_status = 1";
$conversion_result = mysqli_query($connect, $conversion_query);
$conversion_data = mysqli_fetch_assoc($conversion_result);
$conversion_rate = round($conversion_data['conversion_rate'], 2);

// Fetch monthly revenue data for the current year
$revenue_query = "SELECT MONTH(created_at) as month, SUM(amount) as revenue 
                  FROM payments 
                  WHERE YEAR(created_at) = YEAR(CURDATE()) AND payment_status = 1
                  GROUP BY MONTH(created_at)";
$revenue_result = mysqli_query($connect, $revenue_query);

$monthly_revenue = array_fill(0, 12, 0); // Initialize with zeros
while ($row = mysqli_fetch_assoc($revenue_result)) {
    $monthly_revenue[$row['month'] - 1] = $row['revenue'];
}

// Fetch recent transactions
$transactions_query = "SELECT * FROM payments ORDER BY created_at DESC LIMIT 5";
$transactions_result = mysqli_query($connect, $transactions_query);

// Helper functions
function getStatusColor($status) {
  switch ($status) {
    case 0: return 'warning';
    case 1: return 'success';
    default: return 'danger';
  }
}

function getStatusText($status) {
  switch ($status) {
    case 0: return 'Pending';
    case 1: return 'Completed';
    default: return 'Failed';
  }
}
?>

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
        <h6 class="fw-semibold mb-0">Dashboard</h6>
        <ul class="d-flex align-items-center gap-2">
          <li class="fw-medium">
            <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
              <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
              Dashboard
            </a>
          </li>
          <li>-</li>
          <li class="fw-medium">CRM</li>
        </ul>
      </div>
      
      <div class="row gy-4">
        <div class="col-xxl-8">
          <div class="row gy-4">
            
            <!-- New Users Card -->
            <div class="col-xxl-4 col-sm-6">
              <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
                <div class="card-body p-0">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                    <div class="d-flex align-items-center gap-2">
                      <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
                        <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>  
                      </span>
                      <div>
                        <span class="mb-2 fw-medium text-secondary-light text-sm">New Users</span>
                        <h6 class="fw-semibold"><?php echo $total_users; ?></h6>
                      </div>
                    </div>
                    <div id="new-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                  </div>
                  <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+200</span> this week</p>
                </div>
              </div>
            </div>
            
            <!-- Active Users Card -->
            <div class="col-xxl-4 col-sm-6">
              <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2">
                <div class="card-body p-0">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                    <div class="d-flex align-items-center gap-2">
                      <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6">
                        <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>  
                      </span>
                      <div>
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Active Users</span>
                        <h6 class="fw-semibold"><?php echo $active_users; ?></h6>
                      </div>
                    </div>
                    <div id="active-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                  </div>
                  <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+200</span> this week</p>
                </div>
              </div>
            </div>
            
            <!-- Total Sales Card -->
            <div class="col-xxl-4 col-sm-6">
              <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3">
                <div class="card-body p-0">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                    <div class="d-flex align-items-center gap-2">
                      <span class="mb-0 w-48-px h-48-px bg-yellow text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                        <iconify-icon icon="iconamoon:discount-fill" class="icon"></iconify-icon>  
                      </span>
                      <div>
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total Sales</span>
                        <h6 class="fw-semibold">$<?php echo number_format($total_sales, 2); ?></h6>
                      </div>
                    </div>
                    <div id="total-sales-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                  </div>
                  <p class="text-sm mb-0">Increase by <span class="bg-danger-focus px-1 rounded-2 fw-medium text-danger-main text-sm">-$10k</span> this week</p>
                </div>
              </div>
            </div>
            
            <!-- Conversion Card -->
            <div class="col-xxl-4 col-sm-6">
              <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4">
                <div class="card-body p-0">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                    <div class="d-flex align-items-center gap-2">
                      <span class="mb-0 w-48-px h-48-px bg-purple text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                        <iconify-icon icon="mdi:message-text" class="icon"></iconify-icon>  
                      </span>
                      <div>
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Conversion</span>
                        <h6 class="fw-semibold"><?php echo $conversion_rate; ?>%</h6>
                      </div>
                    </div>
                    <div id="conversion-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                  </div>
                  <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+5%</span> this week</p>
                </div>
              </div>
            </div>
            
            <!-- Additional cards... -->

          </div>
        </div>

        <!-- Revenue Growth Chart -->
        <div class="col-xxl-4">
          <div class="card h-100 radius-8 border">
            <div class="card-body p-24">
              <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                <div>
                  <h6 class="mb-2 fw-bold text-lg">Revenue Growth</h6>
                  <span class="text-sm fw-medium text-secondary-light">Weekly Report</span>
                </div>
                <div class="text-end">
                  <h6 class="mb-2 fw-bold text-lg">$<?php echo number_format(array_sum($monthly_revenue), 2); ?></h6>
                  <span class="bg-success-focus ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-success-main text-sm">$10k</span>
                </div>
              </div>
              <div id="revenue-chart" class="mt-28"></div>
            </div>
          </div>
        </div>

        <!-- Earning Statistic Chart -->
        <div class="col-xxl-8">
          <div class="card h-100 radius-8 border-0">
            <div class="card-body p-24">
              <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                <div>
                  <h6 class="mb-2 fw-bold text-lg">Earning Statistic</h6>
                  <span class="text-sm fw-medium text-secondary-light">Yearly earning overview</span>
                </div>
                <div class="">
                  <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                    <option>Yearly</option>
                    <option>Monthly</option>
                    <option>Weekly</option>
                    <option>Today</option>
                  </select>
                </div>
              </div>
              <div id="barChart"></div>
            </div>
          </div>
        </div>

        <!-- Additional sections... -->

        <!-- Latest Performance Table -->
        <div class="col-xxl-6">
          <div class="card h-100">
            <div class="card-header border-bottom bg-base ps-0 py-0 pe-24 d-flex align-items-center justify-content-between">
              <ul class="nav bordered-tab nav-pills mb-0" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active" id="pills-to-do-list-tab" data-bs-toggle="pill" data-bs-target="#pills-to-do-list" type="button" role="tab" aria-controls="pills-to-do-list" aria-selected="true">All Item</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="pills-recent-leads-tab" data-bs-toggle="pill" data-bs-target="#pills-recent-leads" type="button" role="tab" aria-controls="pills-recent-leads" aria-selected="false" tabindex="-1">Best Match</button>
                </li>
              </ul>
              <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                View All
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>
            <div class="card-body p-24">
              <div class="tab-content" id="pills-tabContent">   
                <div class="tab-pane fade show active" id="pills-to-do-list" role="tabpanel" aria-labelledby="pills-to-do-list-tab" tabindex="0">
                  <div class="table-responsive scroll-sm">
                    <table class="table bordered-table mb-0">
                      <thead>
                        <tr>
                          <th scope="col">Task Name </th>
                          <th scope="col">Assigned To </th>
                          <th scope="col">Due Date</th>
                          <th scope="col">Status</th>
                          <th scope="col">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <!-- Sample row, repeat as needed -->
                        <tr>
                          <td>
                            <div>
                              <span class="text-md d-block line-height-1 fw-medium text-primary-light text-w-200-px">Hotel Management System</span>
                              <span class="text-sm d-block fw-normal text-secondary-light">#5632</span>
                            </div>
                          </td>
                          <td>Kathryn Murphy</td>
                          <td>27 Mar 2024</td>
                          <td> <span class="bg-success-focus text-success-main px-24 py-4 rounded-pill fw-medium text-sm">Active</span> </td>
                          <td class="text-center text-neutral-700 text-xl">
                            <div class="dropdown">
                              <button type="button" data-bs-toggle="dropdown" aria-expanded="false"> 
                                <iconify-icon icon="ph:dots-three-outline-vertical-fill" class="icon"></iconify-icon> 
                              </button>
                              <ul class="dropdown-menu p-12 border bg-base shadow">
                                <li><a class="dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900" href="javascript:void(0)">Action</a></li>
                                <li><a class="dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900" href="javascript:void(0)">Another action</a></li>
                                <li><a class="dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900" href="javascript:void(0)">Something else here</a></li>
                              </ul>
                            </div>
                          </td>
                        </tr>
                        <!-- Repeat for more rows -->
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="tab-pane fade" id="pills-recent-leads" role="tabpanel" aria-labelledby="pills-recent-leads-tab" tabindex="0">
                  <!-- Content for Best Match tab -->
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Last Transaction Table -->
        <div class="col-xxl-6">
          <div class="card h-100">
            <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between">
              <h6 class="text-lg fw-semibold mb-0">Last Transaction</h6>
              <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                View All
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>
            <div class="card-body p-24">
              <div class="table-responsive scroll-sm">
                <table class="table bordered-table mb-0">
                  <thead>
                    <tr>
                      <th scope="col">Transaction ID</th>
                      <th scope="col">Date</th>
                      <th scope="col">Status</th>
                      <th scope="col">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($row = mysqli_fetch_assoc($transactions_result)): ?>
                      <tr>
                        <td><?php echo $row['payment_id']; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td>
                          <span class="bg-<?php echo getStatusColor($row['payment_status']); ?>-focus text-<?php echo getStatusColor($row['payment_status']); ?>-main px-24 py-4 rounded-pill fw-medium text-sm">
                            <?php echo getStatusText($row['payment_status']); ?>
                          </span>
                        </td>
                        <td>$<?php echo number_format($row['amount'], 2); ?></td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php include "include/footer.php" ?>
  </main>

  <!-- jQuery library js -->
  <script src="assets/js/lib/jquery-3.7.1.min.js"></script>
  <!-- Bootstrap js -->
  <script src="assets/js/lib/bootstrap.bundle.min.js"></script>
  <!-- Apex Chart js -->
  <script src="assets/js/lib/apexcharts.min.js"></script>
  <!-- Data Table js -->
  <script src="assets/js/lib/dataTables.min.js"></script>
  <!-- Iconify Font js -->
  <script src="assets/js/lib/iconify-icon.min.js"></script>
  <!-- jQuery UI js -->
  <script src="assets/js/lib/jquery-ui.min.js"></script>
  <!-- Vector Map js -->
  <script src="assets/js/lib/jquery-jvectormap-2.0.5.min.js"></script>
  <script src="assets/js/lib/jquery-jvectormap-world-mill-en.js"></script>
  <!-- Popup js -->
  <script src="assets/js/lib/magnifc-popup.min.js"></script>
  <!-- Slick Slider js -->
  <script src="assets/js/lib/slick.min.js"></script>
  <!-- main js -->
  <script src="assets/js/app.js"></script>

  <script>
    // Chart implementations
    function createChart(chartId, chartColor) {
      // Implementation from paste-2.txt
    }

    createChart('new-user-chart', '#487fff');
    createChart('active-user-chart', '#45b369');
    createChart('total-sales-chart', '#f4941e');
    createChart('conversion-user-chart', '#8252e9');

    // Revenue Growth Chart
    var revenueOptions = {
      series: [{
        name: 'Monthly Revenue',
        data: <?php echo json_encode($monthly_revenue); ?>
      }],
      chart: {
        type: 'area',
        height: 162,
        toolbar: { show: false },
      },
      // ... other options from paste-2.txt
    };
    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueOptions);
    revenueChart.render();

    // Earning Statistics Bar Chart
    var earningOptions = {
      // Implementation from paste-2.txt
    };
    var earningChart = new ApexCharts(document.querySelector("#barChart"), earningOptions);
    earningChart.render();

    // Client Payment Status Chart
    var paymentStatusOptions = {
      // Implementation from paste-2.txt
    };
    var paymentStatusChart = new ApexCharts(document.querySelector("#paymentStatusChart"), paymentStatusOptions);
    paymentStatusChart.render();

    // World Map
    $('#world-map').vectorMap({
      // Implementation from paste-2.txt
    });
  </script>
</body>
</html>