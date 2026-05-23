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
    <style>.table-responsive { overflow-x: auto; }</style>
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
            <div class="row mb-4 g-3">
                <div class="col-6 col-md-3">
                    <div class="card border-0 radius-12 p-3 text-center" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe)">
                        <div style="font-size:11px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.5px">Total Trans.</div>
                        <div style="font-size:26px;font-weight:800;color:#4f46e5"><?php echo number_format($stats['total_transactions']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 radius-12 p-3 text-center" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0)">
                        <div style="font-size:11px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.5px">Successful</div>
                        <div style="font-size:26px;font-weight:800;color:#059669"><?php echo number_format($stats['successful_transactions']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 radius-12 p-3 text-center" style="background:linear-gradient(135deg,#fef3c7,#fde68a)">
                        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px">Total Revenue</div>
                        <div style="font-size:22px;font-weight:800;color:#d97706">₹<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 radius-12 p-3 text-center" style="background:linear-gradient(135deg,#e0f2fe,#bae6fd)">
                        <div style="font-size:11px;font-weight:700;color:#075985;text-transform:uppercase;letter-spacing:.5px">Avg. Value</div>
                        <div style="font-size:22px;font-weight:800;color:#0284c7">₹<?php echo number_format($stats['avg_transaction_value'], 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="mos-card-header">
                    <div class="mos-card-header-left">
                        <button class="mos-filter-toggle d-lg-none" data-target="txFilterBody" aria-expanded="false">
                            <iconify-icon icon="heroicons:funnel" style="font-size:15px"></iconify-icon>
                            <span class="toggle-label">Filters</span>
                            <span class="filter-count-badge">0</span>
                        </button>
                        <div class="mos-filter-body d-lg-block" id="txFilterBody">
                            <form method="GET" class="mos-filter-row">
                                <div class="mos-per-page-wrap">
                                    <span>Show</span>
                                    <select name="per_page">
                                        <option value="10" <?php echo $recordsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $recordsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $recordsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $recordsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>
                                <div class="mos-search-wrap">
                                    <iconify-icon icon="ion:search-outline" class="mos-search-icon"></iconify-icon>
                                    <input type="text" name="search" placeholder="Search transactions..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="button" class="mos-search-clear">×</button>
                                </div>
                                <select name="payment_status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="1" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '1') ? 'selected' : ''; ?>>Successful</option>
                                    <option value="0" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == '0') ? 'selected' : ''; ?>>Pending</option>
                                </select>
                                <select name="workshop_type" class="form-select form-select-sm">
                                    <option value="">Workshop Type</option>
                                    <option value="paid" <?php echo (isset($_GET['workshop_type']) && $_GET['workshop_type'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="free" <?php echo (isset($_GET['workshop_type']) && $_GET['workshop_type'] == 'free') ? 'selected' : ''; ?>>Free</option>
                                </select>
                                <select name="workshop_id" class="form-select form-select-sm">
                                    <option value="">All Workshops</option>
                                    <?php foreach ($workshops as $workshop): ?>
                                        <option value="<?php echo $workshop['id']; ?>" <?php echo (isset($_GET['workshop_id']) && $_GET['workshop_id'] == $workshop['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($workshop['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="display:flex;gap:6px;">
                                    <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                                    <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                                </div>
                                <input type="number" name="min_amount" placeholder="Min ₹" value="<?php echo isset($_GET['min_amount']) ? htmlspecialchars($_GET['min_amount']) : ''; ?>" style="width:80px">
                                <input type="number" name="max_amount" placeholder="Max ₹" value="<?php echo isset($_GET['max_amount']) ? htmlspecialchars($_GET['max_amount']) : ''; ?>" style="width:80px">
                                <button type="submit" class="mos-btn-apply">
                                    <iconify-icon icon="heroicons:magnifying-glass" style="font-size:13px"></iconify-icon> Apply
                                </button>
                                <a href="transactions.php" class="mos-btn-reset">
                                    <iconify-icon icon="heroicons:x-mark" style="font-size:13px"></iconify-icon> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                    <div class="mos-card-header-right">
                        <button id="downloadExcel" class="mos-btn-export">
                            <iconify-icon icon="vscode-icons:file-type-excel" style="font-size:16px"></iconify-icon> Export
                        </button>
                    </div>
                </div>
                <div class="mos-active-filters"></div>
                <div class="mos-table-info-bar">
                    <span>Showing <strong><?php echo $offset + 1; ?></strong> – <strong><?php echo min($offset + $recordsPerPage, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> transactions</span>
                </div>
                <div class="card-body p-24">
                    <div class="table-responsive mos-table-wrap">
                        <table class="table bordered-table sm-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" data-sortable>Transaction ID</th>
                                    <th scope="col" data-sortable>Customer</th>
                                    <th scope="col" data-sortable>School</th>
                                    <th scope="col" data-sortable>City</th>
                                    <th scope="col" data-sortable>Workshop</th>
                                    <th scope="col" data-sortable>Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" data-sortable>Date</th>
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

                    <div class="mos-pagination-wrap">
                        <span class="mos-pagination-info">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo number_format($totalRecords); ?> entries</span>
                        <ul class="mos-pagination">
                            <?php if ($page > 1): ?>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" title="First"><iconify-icon icon="ep:d-arrow-left"></iconify-icon></a></li>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹</a></li>
                            <?php else: ?><li class="disabled"><a>‹</a></li><?php endif; ?>
                            <?php
                            $startPage = max(1, $page - 2); $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="<?php echo $i == $page ? 'active' : ''; ?>"><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">›</a></li>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" title="Last"><iconify-icon icon="ep:d-arrow-right"></iconify-icon></a></li>
                            <?php else: ?><li class="disabled"><a>›</a></li><?php endif; ?>
                        </ul>
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