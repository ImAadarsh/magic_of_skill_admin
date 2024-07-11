<?php
include 'include/session.php';
include 'include/connect.php';

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}

// Pagination settings
$recordsPerPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Build the SQL query
$sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.school, u.city, u.id as user_id, w.name as workshop_name 
        FROM payments p
        JOIN users u ON p.user_id = u.id
        JOIN workshops w ON p.workshop_id = w.id";

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR w.name LIKE ? OR p.payment_id LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "sssss";
}

// Payment status filter
if (isset($_GET['payment_status']) && $_GET['payment_status'] != '') {
    $whereClause[] = "p.payment_status = ?";
    $params[] = $_GET['payment_status'];
    $types .= "i";
}



// Workshop filter
if (isset($_GET['workshop_id']) && $_GET['workshop_id'] != '') {
    $whereClause[] = "p.workshop_id = ?";
    $params[] = $_GET['workshop_id'];
    $types .= "i";
}

// Workshop type filter
if (isset($_GET['workshop_type']) && $_GET['workshop_type'] != '') {
    if ($_GET['workshop_type'] == 'paid') {
        $whereClause[] = "p.payment_id LIKE 'MOJO%'";
    } elseif ($_GET['workshop_type'] == 'free') {
        $whereClause[] = "p.payment_id NOT LIKE 'MOJO%'";
    }
}

// Date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] != '' && $_GET['end_date'] != '') {
    $whereClause[] = "p.created_at BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= "ss";
}

// Amount range filter
if (isset($_GET['min_amount']) && $_GET['min_amount'] != '') {
    $whereClause[] = "p.amount >= ?";
    $params[] = $_GET['min_amount'];
    $types .= "d";
}
if (isset($_GET['max_amount']) && $_GET['max_amount'] != '') {
    $whereClause[] = "p.amount <= ?";
    $params[] = $_GET['max_amount'];
    $types .= "d";
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

// Add sorting
$sql .= " ORDER BY p.created_at DESC";

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the query
$stmt = $connect->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $connect->error);
}

if (!empty($types) && !empty($params)) {
    if (!$stmt->bind_param($types, ...$params)) {
        die("Error binding parameters: " . $stmt->error);
    }
}

if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM payments p JOIN users u ON p.user_id = u.id JOIN workshops w ON p.workshop_id = w.id";
if (!empty($whereClause)) {
    $countSql .= " WHERE " . implode(" AND ", $whereClause);
}
$countStmt = $connect->prepare($countSql);
if ($countStmt === false) {
    die("Error preparing count statement: " . $connect->error);
}

if (!empty($params)) {
    // Remove LIMIT and OFFSET params
    array_pop($params);
    array_pop($params);
    $countTypes = substr($types, 0, -2);
    if (!empty($countTypes) && !empty($params)) {
        if (!$countStmt->bind_param($countTypes, ...$params)) {
            die("Error binding count parameters: " . $countStmt->error);
        }
    }
}

if (!$countStmt->execute()) {
    die("Error executing count statement: " . $countStmt->error);
}

$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_row()[0];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch workshops for filter dropdown
$workshopsSql = "SELECT id, name FROM workshops ORDER BY name";
$workshopsResult = $connect->query($workshopsSql);
$workshops = $workshopsResult->fetch_all(MYSQLI_ASSOC);

// Calculate total revenue and other statistics
$statsSql = "SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN payment_status = 1 THEN 1 ELSE 0 END) as successful_transactions,
    SUM(CASE WHEN payment_status = 1 THEN amount ELSE 0 END) as total_revenue,
    AVG(CASE WHEN payment_status = 1 THEN amount ELSE NULL END) as avg_transaction_value
FROM payments";
$statsResult = $connect->query($statsSql);
$stats = $statsResult->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        @media (max-width: 767px) {
            .card-header .d-flex {
                flex-direction: column;
            }
            .card-header .d-flex > * {
                margin-bottom: 10px;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }
        .stats-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stats-card h5 {
            margin-bottom: 15px;
            color: #333;
        }
        .stats-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Payments Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Payments</li>
                </ul>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6>Total Trans.</h6>
                        <div class="stats-value"><?php echo number_format($stats['total_transactions']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6>Successful Trans.</h6>
                        <div class="stats-value"><?php echo number_format($stats['successful_transactions']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6>Total Revenue</h6>
                        <div class="stats-value">₹<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6>Avg. Trans. Value</h6>
                        <div class="stats-value">₹<?php echo number_format($stats['avg_transaction_value'], 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <button id="showFilters" class="btn btn-secondary d-md-none mb-3">Show Filters</button>
                        <div id="filterContainer" class="d-none d-md-block">
                            <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                                <span class="text-md fw-medium text-secondary-light mb-0">Show</span>
                                <select name="per_page" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" onchange="this.form.submit()">
                                    <option value="10" <?php echo $recordsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $recordsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $recordsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $recordsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                                <input type="text" class="bg-base h-40-px w-auto" name="search" placeholder="Search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <select name="payment_status" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                                    <option value="">All Statuses</option>
                                    <option value="1" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '1') ? 'selected' : ''; ?>>Successful</option>
                                    <option value="0" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '0') ? 'selected' : ''; ?>>Pending</option>
                                </select>
                                <select name="workshop_type" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                                    <option value="">All Workshop Types</option>
                                    <option value="paid" <?php echo (isset($_GET['workshop_type']) && $_GET['workshop_type'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="free" <?php echo (isset($_GET['workshop_type']) && $_GET['workshop_type'] == 'free') ? 'selected' : ''; ?>>Free</option>
                                </select>
                                <select name="workshop_id" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                                    <option value="">All Workshops</option>
                                    <?php foreach ($workshops as $workshop): ?>
                                        <option value="<?php echo $workshop['id']; ?>" <?php echo (isset($_GET['workshop_id']) && $_GET['workshop_id'] == $workshop['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($workshop['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" class="form-control h-40-px" placeholder="Start Date">
                                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control h-40-px" placeholder="End Date">
                                <input type="number" name="min_amount" value="<?php echo isset($_GET['min_amount']) ? htmlspecialchars($_GET['min_amount']) : ''; ?>" class="form-control h-40-px" placeholder="Min Amount">
                                <input type="number" name="max_amount" value="<?php echo isset($_GET['max_amount']) ? htmlspecialchars($_GET['max_amount']) : ''; ?>" class="form-control h-40-px" placeholder="Max Amount">
                                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-24">
                    <div class="table-responsive">
                        <table class="table bordered-table sm-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Transaction ID</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">School</th>
                                    <th scope="col">City</th>
                                    <th scope="col">Workshop</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['school']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['city']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['workshop_name']); ?></td>
                                        <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <?php if ($payment['payment_status'] == 1): ?>
                                                <span class="badge bg-success">Successful</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <a href="https://magicofskills.com/invoice/?order_id=<?php echo htmlspecialchars(substr($payment['order_id'], 0, -1)); ?>" target="_blank" class="btn btn-sm btn-info">
                                                    View Invoice
                                                </a>
                                                <a href="view-profile.php?userid=<?php echo htmlspecialchars($payment['user_id']); ?>" class="btn btn-sm btn-secondary">
                                                    View Customer
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-24">
                        <span>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> entries</span>
                        <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">
                                        <iconify-icon icon="ep:d-arrow-left"></iconify-icon>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item">
                                    <a class="page-link <?php echo $i == $page ? 'bg-primary-600 text-white' : 'bg-neutral-300 text-secondary-light'; ?> fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">
                                        <iconify-icon icon="ep:d-arrow-right"></iconify-icon>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="mt-3">
                        <button id="downloadExcel" class="btn btn-success">Download Excel</button>
                    </div>
                </div>
            </div>
        </div>
  
        <?php include "include/footer.php" ?>
    </main>

    <!-- Include your JavaScript files here -->
    <script src="assets/js/lib/jquery-3.7.1.min.js"></script>
    <script src="assets/js/lib/bootstrap.bundle.min.js"></script>
    <script src="assets/js/lib/iconify-icon.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        // Toggle filters on mobile
        document.getElementById('showFilters').addEventListener('click', function() {
            var filterContainer = document.getElementById('filterContainer');
            filterContainer.classList.toggle('d-none');
            filterContainer.classList.toggle('d-block');
        });

        // Excel download functionality
        document.getElementById('downloadExcel').addEventListener('click', function() {
            var table = document.querySelector('table');
            var wb = XLSX.utils.table_to_book(table, {sheet: "Payments"});
            XLSX.writeFile(wb, 'payments.xlsx');
        });

        // View Invoice functionality
        $('.view-invoice').on('click', function() {
            const paymentId = $(this).data('paymentid');
            // Implement the logic to view the invoice
            Swal.fire({
                title: 'View Invoice',
                text: `Viewing invoice for payment ID: ${paymentId}`,
                icon: 'info'
            });
        });

        // View Workshop functionality
        $('.view-workshop').on('click', function() {
            const workshopId = $(this).data('workshopid');
            // Implement the logic to view the workshop details
            Swal.fire({
                title: 'View Workshop',
                text: `Viewing workshop details for ID: ${workshopId}`,
                icon: 'info'
            });
        });

        // View Customer functionality
        $('.view-customer').on('click', function() {
            const userId = $(this).data('userid');
            // Implement the logic to view the customer details
            Swal.fire({
                title: 'View Customer',
                text: `Viewing customer details for ID: ${userId}`,
                icon: 'info'
            });
        });

        // Charts
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Monthly Revenue',
                    data: [12000, 19000, 3000, 5000, 2000, 3000],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Revenue'
                    }
                }
            }
        });

        const statusCtx = document.getElementById('transactionStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Successful', 'Pending'],
                datasets: [{
                    data: [<?php echo $stats['successful_transactions']; ?>, <?php echo $stats['total_transactions'] - $stats['successful_transactions']; ?>],
                    backgroundColor: ['rgb(75, 192, 192)', 'rgb(255, 205, 86)']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Transaction Status'
                    }
                }
            }
        });
    </script>
</body>
</html>