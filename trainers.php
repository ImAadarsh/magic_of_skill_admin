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
$sql = "SELECT id, name, designation, short_description, image, created_at FROM trainers";

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(name LIKE ? OR designation LIKE ? OR short_description LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
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
        case 'this_month':
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $whereClause[] = "DATE(created_at) BETWEEN ? AND ?";
            $params[] = $monthStart;
            $params[] = $monthEnd;
            $types .= "ss";
            break;
        // Add other date filter options as needed
    }
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

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
$trainers = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM trainers";
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

?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainers Management - Magic Of Skills Dashboard</title>
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
                <h6 class="fw-semibold mb-0">Trainers Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Trainers </li>
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
                                <select name="joined" class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" onchange="this.form.submit()">
                                    <option value="">Joined</option>
                                    <option value="today" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="this_month" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <!-- Add other date options as needed -->
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                            </form>
                        </div>
                    </div>
                    <a href="add_trainer.php" class="btn btn-primary">Add New Trainer</a>
                </div>
                <div class="card-body p-24">
                    <div class="table-responsive">
                        <table class="table bordered-table sm-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">S.L</th>
                                    <th scope="col">Join Date</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Designation</th>
                                    <th scope="col">Short Description</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trainers as $index => $trainer): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td><?php echo date('d M Y', strtotime($trainer['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo isset($trainer['image']) ? $uri . $trainer['image'] : "assets/images/default_trainer.png"; ?>" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden">
                                                <div class="flex-grow-1">
                                                    <span class="text-md mb-0 fw-normal text-secondary-light"><?php echo htmlspecialchars($trainer['name']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($trainer['designation']); ?></td>
                                        <td><?php echo htmlspecialchars($trainer['short_description']); ?></td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <a href="https://magicofskills.com/instructor-details.php?id=<?php echo htmlspecialchars($trainer['id']); ?>" type="button" class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"> 
                                                    <iconify-icon icon="majesticons:eye-line" class="icon text-xl"></iconify-icon>
                                                </a>
                                                <a href="edit-trainer.php?id=<?php echo htmlspecialchars($trainer['id']); ?>" type="button" class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"> 
                                                    <iconify-icon icon="lucide:edit" class="menu-icon"></iconify-icon>
                                                </a>
                                                <button type="button" class="delete-trainer bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle" data-trainerid="<?php echo $trainer['id']; ?>"> 
                                                    <iconify-icon icon="fluent:delete-24-regular" class="menu-icon"></iconify-icon>
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
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
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
                                    <a class="page-link <?php echo $i == $page ? 'bg-primary-600 text-white' : 'bg-neutral-300 text-secondary-light'; ?> fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
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
    <?php include "include/script.php" ?>

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
            var wb = XLSX.utils.table_to_book(table, {sheet: "Trainers"});
            XLSX.writeFile(wb, 'trainers.xlsx');
        });

        // Delete trainer functionality
        $('.delete-trainer').on('click', function() {
            const trainerId = $(this).data('trainerid');
            
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
                        url: 'delete/trainer.php',
                        type: 'POST',
                        data: { trainerId: trainerId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire(
                                    'Deleted!',
                                    response.message,
                                    'success'
                                ).then(() => {
                                    // Remove the trainer row from the table
                                    $(`button[data-trainerid="${trainerId}"]`).closest('tr').remove();
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
                                'An error occurred while deleting the trainer. Please check the console for details.',
                                'error'
                            );
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>