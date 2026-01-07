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
$sql = "SELECT id, email, first_name, last_name, school, icon, city, country_code, mobile, user_type, grade, created_at FROM users";

// Fetch distinct grades for filter
$gradesSql = "SELECT DISTINCT grade FROM users WHERE grade IS NOT NULL AND grade != '' ORDER BY grade";
$gradesResult = $connect->query($gradesSql);
$grades = [];
if ($gradesResult) {
    while ($row = $gradesResult->fetch_assoc()) {
        $grades[] = $row['grade'];
    }
}

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// User type filter
if (isset($_GET['user_type']) && $_GET['user_type'] != '') {
    $whereClause[] = "user_type = ?";
    $params[] = $_GET['user_type'];
    $types .= "s";
    $types .= "s";
}

// Grade filter
if (isset($_GET['grade']) && $_GET['grade'] != '') {
    $whereClause[] = "grade = ?";
    $params[] = $_GET['grade'];
    $types .= "s";
}

// Hide Incomplete Users filter
if (isset($_GET['hide_incomplete']) && $_GET['hide_incomplete'] == '1') {
    $whereClause[] = "(email IS NOT NULL AND email != '' AND first_name IS NOT NULL AND first_name != '')";
    // No params needed for this static condition
}

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR school LIKE ? OR city LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "sssss";
}

// Date filter
if (isset($_GET['joined']) && $_GET['joined'] != '') {
    $today = date('Y-m-d');
    switch ($_GET['joined']) {
        case 'today':
            $whereClause[] = "DATE(created_at) = ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $whereClause[] = "DATE(created_at) = ?";
            $params[] = $yesterday;
            $types .= "s";
            break;
        case 'this_week':
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $whereClause[] = "DATE(created_at) BETWEEN ? AND ?";
            $params[] = $weekStart;
            $params[] = $weekEnd;
            $types .= "ss";
            break;
        case 'this_month':
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $whereClause[] = "DATE(created_at) BETWEEN ? AND ?";
            $params[] = $monthStart;
            $params[] = $monthEnd;
            $types .= "ss";
            break;
        case 'custom':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                $startDate = $_GET['start_date'];
                $endDate = $_GET['end_date'];
                $whereClause[] = "DATE(created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
                $types .= "ss";
            }
            break;
    }
}

// School filter
if (isset($_GET['school']) && $_GET['school'] != '') {
    $whereClause[] = "school LIKE ?";
    $params[] = '%' . $_GET['school'] . '%';
    $types .= "s";
}

// City filter
if (isset($_GET['city']) && $_GET['city'] != '') {
    $whereClause[] = "city LIKE ?";
    $params[] = '%' . $_GET['city'] . '%';
    $types .= "s";
}



// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

// Order by latest execution
$sql .= " ORDER BY created_at DESC";

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
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM users";
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

// Continue with your HTML output...
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Of Skills DashBoard</title>
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
                <h6 class="fw-semibold mb-0">Users Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Users </li>
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
                                <select name="user_type" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" onchange="this.form.submit()">
                                    <option value="">All User Types</option>
                                    <option value="admin" <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="user" <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                                <select name="joined" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" onchange="this.form.submit()">
                                    <option value="">Joined</option>
                                    <option value="today" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="yesterday" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                    <option value="this_week" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="this_month" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="custom" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'custom' ? 'selected' : ''; ?>>Custom Dates</option>
                                </select>
                                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" class="form-control">
                                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" class="form-control">
                                <input type="text" class="bg-base h-40-px w-auto" name="school" placeholder="School" value="<?php echo isset($_GET['school']) ? htmlspecialchars($_GET['school']) : ''; ?>">
                                <input type="text" class="bg-base h-40-px w-auto" name="city" placeholder="City" value="<?php echo isset($_GET['city']) ? htmlspecialchars($_GET['city']) : ''; ?>">
                                <select name="status" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <select name="grade" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" onchange="this.form.submit()">
                                    <option value="">All Grades</option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo isset($_GET['grade']) && $_GET['grade'] == $grade ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-check d-flex align-items-center gap-2 mb-0">
                                    <input class="form-check-input mt-0" type="checkbox" name="hide_incomplete" value="1" id="hideIncomplete" <?php echo isset($_GET['hide_incomplete']) && $_GET['hide_incomplete'] == '1' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <label class="form-check-label text-secondary-light" for="hideIncomplete">
                                        Hide Incomplete
                                    </label>
                                </div>
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
                                    <th scope="col">Join Date</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">School</th>
                                    <th scope="col">Grade</th>
                                    <th scope="col">City</th>
                                    <th scope="col">Country</th>
                                    <th scope="col">Mobile</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo isset($user['icon']) ? $uri . $user['icon'] : "assets/images/mos_icon.png"; ?>" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden">
                                                <div class="flex-grow-1">
                                                    <span class="text-md mb-0 fw-normal text-secondary-light"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="text-md mb-0 fw-normal text-secondary-light"><?php echo htmlspecialchars($user['email']); ?></span></td>
                                        <td><?php echo htmlspecialchars($user['school']); ?></td>
                                        <td><?php echo htmlspecialchars($user['grade'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($user['city']); ?></td>
                                        <td><?php echo htmlspecialchars($user['country_code']); ?></td>
                                        <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                        <td class="text-center">
                                            <?php if ($user['user_type'] == 'admin'): ?>
                                                <span class="bg-success-focus text-success-600 border border-success-main px-24 py-4 radius-4 fw-medium text-sm">Admin</span>
                                            <?php else: ?>
                                                <span class="bg-neutral-200 text-neutral-600 border border-neutral-400 px-24 py-4 radius-4 fw-medium text-sm">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <a href="view-profile.php?userid=<?php echo htmlspecialchars($user['id']); ?>" type="button" class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"> 
                                                    <iconify-icon icon="majesticons:eye-line" class="icon text-xl"></iconify-icon>
                                            </a>
                                                <a href="view-profile.php?userid=<?php echo htmlspecialchars($user['id']); ?>" type="button" class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"> 
                                                    <iconify-icon icon="lucide:edit" class="menu-icon"></iconify-icon>
                                                </a>
                                                <button type="button" class="delete-user bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle" data-userid="<?php echo $user['id']; ?>"> 
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
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&user_type=<?php echo urlencode($_GET['user_type'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&school=<?php echo urlencode($_GET['school'] ?? ''); ?>&city=<?php echo urlencode($_GET['city'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>&grade=<?php echo urlencode($_GET['grade'] ?? ''); ?>&hide_incomplete=<?php echo urlencode($_GET['hide_incomplete'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
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
                                    <a class="page-link <?php echo $i == $page ? 'bg-primary-600 text-white' : 'bg-neutral-300 text-secondary-light'; ?> fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&user_type=<?php echo urlencode($_GET['user_type'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&school=<?php echo urlencode($_GET['school'] ?? ''); ?>&city=<?php echo urlencode($_GET['city'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>&grade=<?php echo urlencode($_GET['grade'] ?? ''); ?>&hide_incomplete=<?php echo urlencode($_GET['hide_incomplete'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&user_type=<?php echo urlencode($_GET['user_type'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&school=<?php echo urlencode($_GET['school'] ?? ''); ?>&city=<?php echo urlencode($_GET['city'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>&grade=<?php echo urlencode($_GET['grade'] ?? ''); ?>&hide_incomplete=<?php echo urlencode($_GET['hide_incomplete'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
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

    <!-- Excel export library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

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
            var wb = XLSX.utils.table_to_book(table, {sheet: "Users"});
            XLSX.writeFile(wb, 'users.xlsx');
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('.delete-user').on('click', function() {
        const userId = $(this).data('userid');
        
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
    url: 'delete/user.php',
    type: 'POST',
    data: { userId: userId },
    dataType: 'json',
    success: function(response) {
        if (response.status === 'success') {
            Swal.fire(
                'Deleted!',
                response.message,
                'success'
            ).then(() => {
                // Remove the user row from the table
                $(this).closest('tr').remove();
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
        console.log("Response Text:", jqXHR.responseText);
        Swal.fire(
            'Error!',
            'An error occurred while deleting the user. Please check the console for details.',
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