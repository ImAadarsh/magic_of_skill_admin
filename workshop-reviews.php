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
$sql = "SELECT r.*, u.first_name, u.last_name, w.name as workshop_name 
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN workshops w ON r.workshop_id = w.id";

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR w.name LIKE ? OR r.comment LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

// Rating filter
if (isset($_GET['rating']) && $_GET['rating'] != '') {
    $whereClause[] = "r.rating = ?";
    $params[] = $_GET['rating'];
    $types .= "i";
}

// Workshop filter
if (isset($_GET['workshop_id']) && $_GET['workshop_id'] != '') {
    $whereClause[] = "r.workshop_id = ?";
    $params[] = $_GET['workshop_id'];
    $types .= "i";
}

// Date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] != '' && $_GET['end_date'] != '') {
    $whereClause[] = "r.created_at BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= "ss";
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

// Add sorting
$sql .= " ORDER BY r.created_at DESC";

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
$reviews = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM reviews r JOIN users u ON r.user_id = u.id JOIN workshops w ON r.workshop_id = w.id";
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

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
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
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Reviews Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Reviews</li>
                </ul>
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
                                <select name="rating" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                                    <option value="">All Ratings</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo (isset($_GET['rating']) && $_GET['rating'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select name="workshop_id" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                                    <option value="">All Workshops</option>
                                    <?php foreach ($workshops as $workshop): ?>
                                        <option value="<?php echo $workshop['id']; ?>" <?php echo (isset($_GET['workshop_id']) && $_GET['workshop_id'] == $workshop['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($workshop['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                    <th scope="col">S.L</th>
                                    <th scope="col">User</th>
                                    <th scope="col">Workshop</th>
                                    <th scope="col">Rating</th>
                                    <th scope="col">Comment</th>
                                    <th scope="col">Date</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $index => $review): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($review['workshop_name']); ?></td>
                                        <td><?php echo $review['rating']; ?></td>
                                        <td><?php echo htmlspecialchars(substr($review['comment'], 0, 50)) . (strlen($review['comment']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo date('d M Y', strtotime($review['created_at'])); ?></td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <button type="button" class="delete-review bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle" data-reviewid="<?php echo $review['id']; ?>"> 
                                                    <iconify-icon icon="fluent:delete-24-regular" class="menu-icon"></iconify-icon>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            var wb = XLSX.utils.table_to_book(table, {sheet: "Reviews"});
            XLSX.writeFile(wb, 'reviews.xlsx');
        });

        // Review deletion functionality
        $(document).ready(function() {
            $('.delete-review').on('click', function() {
                const reviewId = $(this).data('reviewid');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'delete/ws-review.php',
                            type: 'POST',
                            data: { reviewId: reviewId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire(
                                        'Deleted!',
                                        response.message,
                                        'success'
                                    ).then(() => {
                                        location.reload(); // Reload the page to reflect the changes
                                    });
                                } else {
                                    Swal.fire(
                                        'Error!',
                                        response.message,
                                        'error'
                                    );
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                console.error("AJAX Error:", textStatus, errorThrown);
                                Swal.fire(
                                    'Error!',
                                    'An error occurred while deleting the review. Please check the console for details.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });
        });
    </script>
    
</body>
</html>