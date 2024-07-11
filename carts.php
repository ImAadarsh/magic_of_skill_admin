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
$sql = "SELECT c.*, u.first_name, u.last_name, u.email, u.school, u.city, u.id as user_id,
               COUNT(i.id) as item_count, SUM(i.price) as total_price
        FROM carts c
        JOIN users u ON c.user_id = u.id
        JOIN items i ON c.id = i.cart_id
        GROUP BY c.id
        HAVING item_count >= 1";

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR c.payment_id LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

// Payment status filter
if (isset($_GET['is_bought']) && $_GET['is_bought'] != '') {
    $whereClause[] = "c.is_bought = ?";
    $params[] = $_GET['is_bought'];
    $types .= "i";
}

// Date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] != '' && $_GET['end_date'] != '') {
    $whereClause[] = "c.created_at BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= "ss";
}

// Price range filter
if (isset($_GET['min_price']) && $_GET['min_price'] != '') {
    $whereClause[] = "total_price >= ?";
    $params[] = $_GET['min_price'];
    $types .= "d";
}
if (isset($_GET['max_price']) && $_GET['max_price'] != '') {
    $whereClause[] = "total_price <= ?";
    $params[] = $_GET['max_price'];
    $types .= "d";
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " AND " . implode(" AND ", $whereClause);
}

// Add sorting
$sql .= " ORDER BY c.created_at DESC";

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
$carts = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM (
    SELECT c.id
    FROM carts c
    JOIN users u ON c.user_id = u.id
    JOIN items i ON c.id = i.cart_id
    GROUP BY c.id
    HAVING COUNT(i.id) >= 1
) as cart_count";

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

// Calculate total revenue and other statistics
$statsSql = "SELECT 
    COUNT(*) as total_carts,
    SUM(CASE WHEN is_bought = 1 THEN 1 ELSE 0 END) as completed_carts,
    SUM(total_price) as total_revenue,
    AVG(total_price) as avg_cart_value
FROM (
    SELECT c.id, c.is_bought, SUM(i.price) as total_price
    FROM carts c
    JOIN items i ON c.id = i.cart_id
    GROUP BY c.id
    HAVING COUNT(i.id) >= 1
) as cart_stats";
$statsResult = $connect->query($statsSql);
$stats = $statsResult->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carts - Magic Of Skills Dashboard</title>
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
        .stats-card h6 {
            margin-bottom: 15px;
            color: #333;
        }
        .stats-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }

    .swal-custom-container {
        font-family: 'Arial', sans-serif;
    }

    .swal-custom-popup {
        background: #f9f9f9;
        border-radius: 15px;
    }

    .swal-custom-header {
        background: #007bff;
        color: black;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
    }

    .swal-title {
        color: black;
        font-size: 33px;
    }

    .swal-custom-content {
        padding: 0;
    }

    .swal-custom-confirm-button {
        background: #007bff !important;
    }

    .cart-details {
        color: #333;
    }

    .section-title {
        background: #007bff;
        color: white;
        padding: 10px 15px;
        margin: 0;
        font-size: 20px !important;
    }

    .info-grid, .summary-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 15px;
    }

    .info-item, .summary-item {
        background: white;
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .label {
        font-weight: bold;
        color: #555;
    }

    .value {
        color: #007bff;
    }

    .total {
        grid-column: 1 / -1;
        background: #e9f5ff;
        font-size: 1.1em;
    }

    .payment-info {
        padding: 15px;
        text-align: center;
    }

    .payment-status {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: bold;
        margin-bottom: 18px;
        margin-top: 15px;
    }

    .payment-status.paid {
        background: #28a745;
        color: white;
    }

    .payment-status.pending {
        background: #ffc107;
        color: #333;
    }

    .created-at {
        color: #6c757d;
    }

    .cart-items {
        padding: 15px;
    }

    .item-list {
        list-style-type: none;
        padding: 0;
        max-height: 200px;
        overflow-y: auto;
    }

    .item-list li {
        background: white;
        margin-bottom: 8px;
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .fas {
        margin-right: 5px;
    }
</style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
        <div class="alert alert-info" role="alert">
                Showing carts with one or more items only.
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Carts Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Carts</li>
                </ul>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6>Total Carts</h6>
                        <div class="stats-value"><?php echo number_format($stats['total_carts']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6>Completed Carts</h6>
                        <div class="stats-value"><?php echo number_format($stats['completed_carts']); ?></div>
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
                        <h6>Avg. Cart Value</h6>
                        <div class="stats-value">₹<?php echo number_format($stats['avg_cart_value'], 2); ?></div>
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
                                <select name="is_bought" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                                    <option value="">All Statuses</option>
                                    <option value="1" <?php echo (isset($_GET['is_bought']) && $_GET['is_bought'] == '1') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="0" <?php echo (isset($_GET['is_bought']) && $_GET['is_bought'] == '0') ? 'selected' : ''; ?>>Pending</option>
                                </select>
                                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" class="form-control h-40-px" placeholder="Start Date">
                                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control h-40-px" placeholder="End Date">
                                <input type="number" name="min_price" value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>" class="form-control h-40-px" placeholder="Min Price">
                                <input type="number" name="max_price" value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>" class="form-control h-40-px" placeholder="Max Price">
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
                                    <th scope="col">Cart ID</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Items</th>
                                    <th scope="col">Total Price</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carts as $cart): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cart['id']); ?></td>
                                        <td><?php echo htmlspecialchars($cart['first_name'] . ' ' . $cart['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cart['item_count']); ?></td>
                                        <td>₹<?php echo number_format($cart['total_price'], 2); ?></td>
                                        <td>
                                            <?php if ($cart['is_bought'] == 1): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($cart['created_at'])); ?></td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <button type="button" class="btn btn-sm btn-info view-cart-details" data-cartid="<?php echo $cart['id']; ?>">
                                                    View Details
                                                </button>
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
            var wb = XLSX.utils.table_to_book(table, {sheet: "Carts"});
            XLSX.writeFile(wb, 'carts.xlsx');
        });

      // View Cart Details functionality
      $('.view-cart-details').on('click', function() {
            const cartId = $(this).data('cartid');
            
            // AJAX request to fetch cart details
            $.ajax({
                url: 'get_cart_details.php',
                type: 'GET',
                data: { cartId: cartId },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        let itemsList = response.items.map(item => 
                            `<li>${item.workshop_name} - ₹${item.price.toFixed(2)}</li>`
                        ).join('');

                        Swal.fire({
    title: '<h2 class="swal-title"><i class="fas fa-shopping-cart"></i> Cart Details</h2>',
    html: `
        <div class="cart-details">
            <div class="customer-info">
                <h3 class="section-title"><i class="fas fa-user-circle"></i> Customer Information</h3>
                <div class="info-grid">
                    <div class="info-item"><span class="label"><i class="fas fa-user"></i> Name: <br></span> <span class="value">${response.customer_name}</span></div>
                    <div class="info-item"><span class="label"><i class="fas fa-envelope"></i> Email: <br></span> <span class="value">${response.email}</span></div>
                    <div class="info-item"><span class="label"><i class="fas fa-school"></i> School: <br></span> <span class="value">${response.school}</span></div>
                    <div class="info-item"><span class="label"><i class="fas fa-map-marker-alt"></i> City: <br></span> <span class="value">${response.city}</span></div>
                </div>
            </div>

            <div class="order-summary">
                <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Order Summary</h3>
                <div class="summary-grid">
                    <div class="summary-item"><span class="label"><i class="fas fa-list"></i> Total Items:</span> <span class="value">${response.items.length}</span></div>
                    <div class="summary-item"><span class="label"><i class="fas fa-tag"></i> Total Price:</span> <span class="value">₹${response.total_price.toFixed(2)}</span></div>
                    <div class="summary-item"><span class="label"><i class="fas fa-ticket-alt"></i> Coupon:</span> <span class="value">${response.coupon_code || 'None'}</span></div>
                    <div class="summary-item"><span class="label"><i class="fas fa-percent"></i> Discount:</span> <span class="value">₹${response.discount.toFixed(2)}</span></div>
                    <div class="summary-item total"><span class="label"><i class="fas fa-money-bill-wave"></i> Final Price:</span> <span class="value">₹${response.final_price.toFixed(2)}</span></div>
                </div>
            </div>

            <div class="payment-info">
                <h3 class="section-title"><i class="fas fa-credit-card"></i> Payment Information</h3>
                <div class="payment-status ${response.payment_status ? 'paid' : 'pending'}">
                    <i class="fas ${response.payment_status ? 'fa-check-circle' : 'fa-clock'}"></i>
                    ${response.payment_status ? 'Paid' : 'Pending'}
                </div>
                <div class="created-at">
                    <i class="fas fa-calendar-alt"></i> Created: ${response.created_at}
                </div>
            </div>

            <div class="cart-items">
                <h3 class="section-title"><i class="fas fa-box-open"></i> Items in Cart</h3>
                <ul class="item-list">
                    ${itemsList}
                </ul>
            </div>
        </div>
    `,
    width: 800,
    confirmButtonText: 'Close',
    showCloseButton: true,
    customClass: {
        container: 'swal-custom-container',
        popup: 'swal-custom-popup',
        header: 'swal-custom-header',
        content: 'swal-custom-content',
        confirmButton: 'swal-custom-confirm-button'
    }
});
                    } else {
                        Swal.fire('Error', 'Failed to fetch cart details', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'An error occurred while fetching cart details', 'error');
                }
            });
        });
    </script>
</body>
</html>