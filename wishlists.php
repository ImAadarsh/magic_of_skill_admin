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
$sql = "SELECT w.*, u.first_name, u.last_name, u.email, u.school, u.city, u.id as user_id,
               ws.name as workshop_name, ws.price as workshop_price
        FROM wishlists w
        JOIN users u ON w.user_id = u.id
        JOIN workshops ws ON w.workshop_id = ws.id";

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ws.name LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

// Date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] != '' && $_GET['end_date'] != '') {
    $whereClause[] = "w.created_at BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= "ss";
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

// Add sorting
$sql .= " ORDER BY w.created_at DESC";

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
$wishlists = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM wishlists w JOIN users u ON w.user_id = u.id JOIN workshops ws ON w.workshop_id = ws.id";
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

// Calculate statistics
$statsSql = "SELECT 
    COUNT(DISTINCT user_id) as total_users,
    COUNT(*) as total_wishlists,
    AVG(ws.price) as avg_workshop_price
FROM wishlists w
JOIN workshops ws ON w.workshop_id = ws.id";
$statsResult = $connect->query($statsSql);
$stats = $statsResult->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlists - Magic Of Skills Dashboard</title>
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
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Wishlists Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Wishlists</li>
                </ul>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <h6>Total Users with Wishlists</h6>
                        <div class="stats-value"><?php echo number_format($stats['total_users']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h6>Total Wishlisted Items</h6>
                        <div class="stats-value"><?php echo number_format($stats['total_wishlists']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h6>Avg. Workshop Price</h6>
                        <div class="stats-value">₹<?php echo number_format($stats['avg_workshop_price'], 2); ?></div>
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
                                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" class="form-control h-40-px" placeholder="Start Date">
                                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control h-40-px" placeholder="End Date">
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
                                    <th scope="col">Wishlist ID</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Workshop</th>
                                    <th scope="col">Workshop Price</th>
                                    <th scope="col">Date Added</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wishlists as $wishlist): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($wishlist['id']); ?></td>
                                        <td><?php echo htmlspecialchars($wishlist['first_name'] . ' ' . $wishlist['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($wishlist['workshop_name']); ?></td>
                                        <td>₹<?php echo number_format($wishlist['workshop_price'], 2); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($wishlist['created_at'])); ?></td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <button type="button" class="btn btn-sm btn-info view-wishlist-details" data-wishlistid="<?php echo $wishlist['id']; ?>">
                                                    View Details
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
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
            var wb = XLSX.utils.table_to_book(table, {sheet: "Wishlists"});
            XLSX.writeFile(wb, 'wishlists.xlsx');
        });

// View Wishlist Details functionality
$('.view-wishlist-details').on('click', function() {
            const wishlistId = $(this).data('wishlistid');
            
            // AJAX request to fetch wishlist details
            $.ajax({
                url: 'get_wishlist_details.php',
                type: 'GET',
                data: { wishlistId: wishlistId },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        Swal.fire({
                            title: '<h2 class="swal-title"><i class="fas fa-heart"></i> Wishlist Details</h2>',
                            html: `
                                <div class="wishlist-details">
                                    <div class="customer-info">
                                        <h3 class="section-title"><i class="fas fa-user-circle"></i> Customer Information</h3>
                                        <div class="info-grid">
                                            <div class="info-item"><span class="label"><i class="fas fa-user"></i> Name: </span> <span class="value">${response.customer_name}</span></div>
                                            <div class="info-item"><span class="label"><i class="fas fa-envelope"></i> Email: </span> <span class="value">${response.email}</span></div>
                                            <div class="info-item"><span class="label"><i class="fas fa-school"></i> School: </span> <span class="value">${response.school}</span></div>
                                            <div class="info-item"><span class="label"><i class="fas fa-map-marker-alt"></i> City: </span> <span class="value">${response.city}</span></div>
                                        </div>
                                    </div>

                                    <div class="workshop-info">
                                        <h3 class="section-title"><i class="fas fa-chalkboard-teacher"></i> Workshop Information</h3>
                                        <div class="info-grid">
                                            <div class="info-item"><span class="label"><i class="fas fa-book"></i> Name: </span> <span class="value">${response.workshop_name}</span></div>
                                            <div class="info-item"><span class="label"><i class="fas fa-tag"></i> Price: </span> <span class="value">₹${response.workshop_price.toFixed(2)}</span></div>
                                            <div class="info-item"><span class="label"><i class="fas fa-calendar-alt"></i> Start Date: </span> <span class="value">${response.workshop_start_date}</span></div>
                                            <div class="info-item"><span class="label"><i class="fas fa-clock"></i> Duration: </span> <span class="value">${response.workshop_duration} minutes</span></div>
                                        </div>
                                    </div>

                                    <div class="wishlist-info">
                                        <h3 class="section-title"><i class="fas fa-info-circle"></i> Wishlist Information</h3>
                                        <div class="info-grid">
                                            <div class="info-item"><span class="label"><i class="fas fa-calendar-plus"></i> Added On: </span> <span class="value">${response.created_at}</span></div>
                                        </div>
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
                        Swal.fire('Error', 'Failed to fetch wishlist details', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'An error occurred while fetching wishlist details', 'error');
                }
            });
        });
    </script>
</body>
</html>