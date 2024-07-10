<?php include "include/connection.php"; ?>
<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Magic Of Skills DashBoard</title>
  <?php include "include/meta.php"; ?>
</head>
<body>
  <?php include "include/aside.php"; ?>

<main class="dashboard-main">
  <?php include "include/header.php"; ?>

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
        <li class="fw-medium">Hello <?php echo $name; ?>!</li>
      </ul>
    </div>
    
    <div class="row gy-4">
      <div class="col-xxl-9">
        <div class="card radius-8 border-0">
          <div class="row">
            <div class="col-xxl-6 pe-xxl-0">
              <div class="card-body p-24">
                <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                  <h6 class="mb-2 fw-bold text-lg">Revenue <?php echo $usertype; ?></h6>
                  <div class="">
                    <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                      <option>Yearly</option>
                      <option>Monthly</option>
                      <option>Weekly</option>
                      <option>Today</option>
                    </select>
                  </div>
                </div>
                <ul class="d-flex flex-wrap align-items-center mt-3 gap-3">
                  <li class="d-flex align-items-center gap-2">
                    <span class="w-12-px h-12-px radius-2 bg-primary-600"></span>
                    <span class="text-secondary-light text-sm fw-semibold">Earning: 
                      <span class="text-primary-light fw-bold">
                        <?php
                          $sql = "SELECT SUM(amount) AS total_earnings FROM payments WHERE payment_status = 1";
                          $result = $connect->query($sql);
                          $row = $result->fetch_assoc();
                          echo "₹" . number_format($row['total_earnings'], 2);

                        ?>
                      </span>
                    </span>
                  </li>
                  
                </ul>
                <div class="mt-40">
                  <div id="paymentStatusChart" class="margin-16-minus"></div>
                </div>
              </div>
            </div>
            <div class="col-xxl-6">
              <div class="row h-100 g-0">
                <div class="col-6 p-0 m-0">
                  <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                      <div>
                        <span class="mb-12 w-44-px h-44-px text-primary-600 bg-primary-light border border-primary-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                          <iconify-icon icon="fa-solid:box-open" class="icon"></iconify-icon>  
                        </span>
                        <span class="mb-1 fw-medium text-secondary-light text-md">Total Workshops</span>
                        <h6 class="fw-semibold text-primary-light mb-1">
                          <?php
                            $sql = "SELECT COUNT(*) AS total_workshops FROM workshops";
                            $result = $connect->query($sql);
                            $row = $result->fetch_assoc();
                            echo $row['total_workshops'];
                          ?>
                        </h6>
                      </div>
                    </div>
                    <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+5</span> this week</p>
                  </div>
                </div>
                <div class="col-6 p-0 m-0">
                  <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0 border-start-0 border-end-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                      <div>
                        <span class="mb-12 w-44-px h-44-px text-yellow bg-yellow-light border border-yellow-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                          <iconify-icon icon="flowbite:users-group-solid" class="icon"></iconify-icon>  
                        </span>
                        <span class="mb-1 fw-medium text-secondary-light text-md">Total Users</span>
                        <h6 class="fw-semibold text-primary-light mb-1">
                          <?php
                            $sql = "SELECT COUNT(*) AS total_users FROM users";
                            $result = $connect->query($sql);
                            $row = $result->fetch_assoc();
                            echo $row['total_users'];
                          ?>
                        </h6>
                      </div>
                    </div>
                    <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+20</span> this week</p>
                  </div>
                </div>
                <div class="col-6 p-0 m-0">
                  <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0 border-bottom-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                      <div>
                        <span class="mb-12 w-44-px h-44-px text-lilac bg-lilac-light border border-lilac-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                          <iconify-icon icon="majesticons:shopping-cart" class="icon"></iconify-icon>  
                        </span>
                        <span class="mb-1 fw-medium text-secondary-light text-md">Total Orders</span>
                        <h6 class="fw-semibold text-primary-light mb-1">
                          <?php
                            $sql = "SELECT COUNT(*) AS total_orders FROM payments WHERE payment_status = 1";
                            $result = $connect->query($sql);
                            $row = $result->fetch_assoc();
                            echo $row['total_orders'];
                          ?>
                        </h6>
                      </div>
                    </div>
                    <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+10</span> this week</p>
                  </div>
                </div>
                <div class="col-6 p-0 m-0">
                  <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0 border-start-0 border-end-0 border-bottom-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                      <div>
                        <span class="mb-12 w-44-px h-44-px text-pink bg-pink-light border border-pink-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                          <iconify-icon icon="ri:discount-percent-fill" class="icon"></iconify-icon>  
                        </span>
                        <span class="mb-1 fw-medium text-secondary-light text-md">Total Sales</span>
                        <h6 class="fw-semibold text-primary-light mb-1">
                          <?php
                            $sql = "SELECT SUM(amount) AS total_sales FROM payments WHERE payment_status = 1";
                            $result = $connect->query($sql);
                            $row = $result->fetch_assoc();
                            echo "₹" . number_format($row['total_sales'], 2);
                          ?>
                        </h6>
                      </div>
                    </div>
                    <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+$1000</span> this week</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-3 col-lg-6">
        <div class="card h-100 radius-8 border-0">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg">User Statistics</h6>
              <div class="">
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>Yearly</option>
                  <option>Monthly</option>
                  <option>Weekly</option>
                  <option>Today</option>
                </select>
              </div>
            </div>

            <div class="position-relative">
              <span class="w-80-px h-80-px bg-base shadow text-primary-light fw-semibold text-xl d-flex justify-content-center align-items-center rounded-circle position-absolute end-0 top-0 z-1">+30%</span>
              <div id="statisticsDonutChart" class="mt-36 flex-grow-1 apexcharts-tooltip-z-none title-style circle-none"></div>
              <span class="w-80-px h-80-px bg-base shadow text-primary-light fw-semibold text-xl d-flex justify-content-center align-items-center rounded-circle position-absolute start-0 bottom-0 z-1">+25%</span>
            </div>
            
            <ul class="d-flex flex-wrap align-items-center justify-content-between mt-3 gap-3">
              <li class="d-flex align-items-center gap-2">
                <span class="w-12-px h-12-px radius-2 bg-primary-600"></span>
                <span class="text-secondary-light text-sm fw-normal">Students: 
                  <span class="text-primary-light fw-bold">
                    <?php
                      $sql = "SELECT COUNT(*) AS student_count FROM users WHERE user_type = 'user'";
                      $result = $connect->query($sql);
                      $row = $result->fetch_assoc();
                      echo $row['student_count'];
                    ?>
                  </span>
                </span>
              </li>
              <li class="d-flex align-items-center gap-2">
                <span class="w-12-px h-12-px radius-2 bg-yellow"></span>
                <span class="text-secondary-light text-sm fw-normal">Trainers:  
                  <span class="text-primary-light fw-bold">
                    <?php
                      $sql = "SELECT COUNT(*) AS trainer_count FROM trainers";
                      $result = $connect->query($sql);
                      $row = $result->fetch_assoc();
                      echo $row['trainer_count'];
                    ?>
                  </span>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-xxl-8 col-lg-6">
        <div class="card h-100">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-20">
              <h6 class="mb-2 fw-bold text-lg mb-0">Recent Orders</h6>
              <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                View All
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>
            <div class="table-responsive scroll-sm">
              <table class="table bordered-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Users</th>
                    <th scope="col">Workshop</th>
                    <th scope="col">Amount</th>
                    <th scope="col" class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $sql = "SELECT p.*, u.*, w.name AS workshop_name 
                            FROM payments p 
                            JOIN users u ON p.user_id = u.id 
                            JOIN workshops w ON p.workshop_id = w.id 
                            ORDER BY p.created_at DESC LIMIT 5";
                    $result = $connect->query($sql);
                    while($row = $result->fetch_assoc()) {
                      echo "<tr>
                              <td>
                                <div class='d-flex align-items-center'>
                                  <img src='{$uri}{$row['icon']}' alt='' class='flex-shrink-0 me-12 radius-8'>
                                  <span class='text-lg text-secondary-light fw-semibold flex-grow-1'>{$row['first_name']} {$row['last_name']}</span>
                                </div>
                              </td>
                              <td>{$row['workshop_name']}</td>
                              <td>₹{$row['amount']}</td>
                             <td class='text-center'>";
                      if($row['payment_status'] == 1) {
                        echo "<span class='bg-success-focus text-success-main px-24 py-4 rounded-pill fw-medium text-sm'>Paid</span>";
                      } else {
                        echo "<span class='bg-warning-focus text-warning-main px-24 py-4 rounded-pill fw-medium text-sm'>Pending</span>";
                      }
                      echo "</td>
                            </tr>";
                    }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg">Transactions</h6>
              <div class="">
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>This Month</option>
                  <option>Last Month</option>
                </select>
              </div>
            </div>
  
            <div class="mt-32">
              <?php
                $sql = "SELECT p.*, w.name AS workshop_name 
                        FROM payments p 
                        JOIN workshops w ON p.workshop_id = w.id 
                        ORDER BY p.created_at DESC LIMIT 5";
                $result = $connect->query($sql);
                while($row = $result->fetch_assoc()) {
                  echo "<div class='d-flex align-items-center justify-content-between gap-3 mb-32'>
                          <div class='d-flex align-items-center gap-2'>
                            <img src='assets/images/payment/payment1.png' alt='' class='w-40-px h-40-px radius-8 flex-shrink-0'>
                            <div class='flex-grow-1'>
                              <h6 class='text-md mb-0 fw-normal'>{$row['workshop_name']}</h6>
                              <span class='text-sm text-secondary-light fw-normal'>" . date('M d, Y', strtotime($row['created_at'])) . "</span>
                            </div>
                          </div>
                          <span class='text-" . ($row['payment_status'] == 1 ? 'success' : 'danger') . " text-md fw-medium'>" . ($row['payment_status'] == 1 ? '+' : '-') . "₹{$row['amount']}</span>
                        </div>";
                }
              ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-7">
        <div class="card h-100 radius-8 border">
          <div class="card-body p-24">
            <h6 class="mb-12 fw-bold text-lg mb-0">Recent Orders</h6>
            <div class="d-flex align-items-center gap-2">
              <h6 class="fw-semibold mb-0">
                <?php
                  $sql = "SELECT SUM(amount) AS total_amount FROM payments WHERE payment_status = 1";
                  $result = $connect->query($sql);
                  $row = $result->fetch_assoc();
                  echo "₹" . number_format($row['total_amount'], 2);
                ?>
              </h6>
              <p class="text-sm mb-0">
                <span class="bg-success-focus border border-success px-8 py-2 rounded-pill fw-semibold text-success-main text-sm d-inline-flex align-items-center gap-1">
                  10%
                  <iconify-icon icon="iconamoon:arrow-up-2-fill" class="icon"></iconify-icon>  
                </span> 
                Increases 
              </p>
            </div>
            <div id="recent-orders" class="mt-28"></div>
          </div>
        </div>
      </div>
     
      <div class="col-xxl-5 col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-20">
              <h6 class="mb-2 fw-bold text-lg mb-0">Top Trainers</h6>
              <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                View All
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>

            <div class="mt-32">
              <?php
                $sql = "SELECT t.*, COUNT(w.id) as workshop_count 
                        FROM trainers t 
                        LEFT JOIN workshops w ON t.id = w.trainer_id 
                        GROUP BY t.id 
                        ORDER BY workshop_count DESC 
                        LIMIT 5";
                $result = $connect->query($sql);
                while($row = $result->fetch_assoc()) {
                  echo "<div class='d-flex align-items-center justify-content-between gap-3 mb-32'>
                          <div class='d-flex align-items-center gap-2'>
                            <img src='{$uri}{$row['image']}' alt='' class='w-40-px h-40-px radius-8 flex-shrink-0'>
                            <div class='flex-grow-1'>
                              <h6 class='text-md mb-0 fw-normal'>{$row['name']}</h6>
                              <span class='text-sm text-secondary-light fw-normal'>{$row['designation']}</span>
                            </div>
                          </div>
                          <span class='text-primary-light text-md fw-medium'>Workshops: {$row['workshop_count']}</span>
                        </div>";
                }
              ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-6">
        <div class="card h-100">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-20">
              <h6 class="mb-2 fw-bold text-lg mb-0">Top Selling Workshops</h6>
              <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                View All
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>
            <div class="table-responsive scroll-sm">
              <table class="table bordered-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Workshop</th>
                    <th scope="col">Price</th>
                    <th scope="col">Sold</th>
                    <th scope="col" class="text-center">Total Orders</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $sql = "SELECT w.*, COUNT(p.id) as order_count, SUM(p.amount) as total_sales 
                            FROM workshops w 
                            LEFT JOIN payments p ON w.id = p.workshop_id 
                            WHERE p.payment_status = 1 
                            GROUP BY w.id 
                            ORDER BY total_sales DESC 
                            LIMIT 5";
                    $result = $connect->query($sql);
                    while($row = $result->fetch_assoc()) {
                      echo "<tr>
                              <td>
                                <div class='d-flex align-items-center'>
                                  <img src='{$row['icon']}' alt='' class='flex-shrink-0 me-12 radius-8 me-12'>
                                  <div class='flex-grow-1'>
                                    <h6 class='text-md mb-0 fw-normal'>{$row['name']}</h6>
                                    <span class='text-sm text-secondary-light fw-normal'>" . substr($row['short_description'], 0, 30) . "...</span>
                                  </div>
                                </div>
                              </td>
                              <td>₹{$row['price']}</td>
                              <td>{$row['order_count']}</td>
                              <td class='text-center'> 
                                <span class='bg-success-focus text-success-main px-32 py-4 rounded-pill fw-medium text-sm'>\$" . number_format($row['total_sales'], 2) . "</span> 
                              </td>
                            </tr>";
                    }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-6">
        <div class="card h-100">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-20">
              <h6 class="mb-2 fw-bold text-lg mb-0">Workshop Report</h6>
              <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                View All
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>
            <div class="table-responsive scroll-sm">
              <table class="table bordered-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Workshop</th>
                    <th scope="col">Price</th>
                    <th scope="col">
                      <div class="max-w-112 mx-auto">
                        <span>Popularity</span>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $popularity = 0;
                  $total_orders=5;
                    $sql = "SELECT w.*, COUNT(p.id) as order_count 
                            FROM workshops w 
                            LEFT JOIN payments p ON w.id = p.workshop_id 
                            WHERE p.payment_status = 1 
                            GROUP BY w.id 
                            ORDER BY order_count DESC 
                            LIMIT 5";
                    
                    $result = $connect->query($sql);
                
                    while($row = $result->fetch_assoc()) {
         
                      $popularity = ($row['order_count'] / $total_orders) * 100;
               
                      echo "<tr>
                              <td>{$row['name']}</td>
                              <td>Rs{$row['price']}</td>
                              <td> 
                                <div class='max-w-112 mx-auto'>
                                  <div class='w-100'>
                                    <div class='progress progress-sm rounded-pill' role='progressbar' aria-label='Success example' aria-valuenow='{$popularity}' aria-valuemin='0' aria-valuemax='100'>
                                      <div class='progress-bar bg-success-main rounded-pill' style='width: {$popularity}%;'></div>
                                    </div>
                                  </div>
                                  <span class='mt-12 text-secondary-light text-sm fw-medium'>" . number_format($popularity, 2) . "% Popularity</span>                                
                                </div>
                              </td>
                            </tr>";
                    }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php include "include/footer.php"; ?>
</main>
<?php include "include/script.php"; ?>

<script src="assets/js/homeThreeChart.js"></script>

</body>
</html>