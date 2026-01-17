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
$sql = "SELECT wf.*, w.title as workshop_title 
        FROM workshop_feedback wf 
        LEFT JOIN workshops w ON wf.workshop_id = w.id";

// Fetch workshops for filter
$workshopsSql = "SELECT id, title FROM workshops ORDER BY title";
$workshopsResult = $connect->query($workshopsSql);
$workshops = [];
if ($workshopsResult) {
    while ($row = $workshopsResult->fetch_assoc()) {
        $workshops[] = $row;
    }
}

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Workshop filter
if (isset($_GET['workshop_id']) && $_GET['workshop_id'] != '') {
    $whereClause[] = "wf.workshop_id = ?";
    $params[] = $_GET['workshop_id'];
    $types .= "i";
}

// Rating filter
if (isset($_GET['rating']) && $_GET['rating'] != '') {
    $whereClause[] = "wf.rating = ?";
    $params[] = $_GET['rating'];
    $types .= "i";
}

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(wf.full_name LIKE ? OR wf.email LIKE ? OR wf.school_name LIKE ? OR wf.city LIKE ? OR wf.phone LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "sssss";
}

// Date filter
if (isset($_GET['joined']) && $_GET['joined'] != '') {
    $today = date('Y-m-d');
    switch ($_GET['joined']) {
        case 'today':
            $whereClause[] = "DATE(wf.created_at) = ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $whereClause[] = "DATE(wf.created_at) = ?";
            $params[] = $yesterday;
            $types .= "s";
            break;
        case 'this_week':
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $whereClause[] = "DATE(wf.created_at) BETWEEN ? AND ?";
            $params[] = $weekStart;
            $params[] = $weekEnd;
            $types .= "ss";
            break;
        case 'this_month':
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $whereClause[] = "DATE(wf.created_at) BETWEEN ? AND ?";
            $params[] = $monthStart;
            $params[] = $monthEnd;
            $types .= "ss";
            break;
        case 'custom':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                $startDate = $_GET['start_date'];
                $endDate = $_GET['end_date'];
                $whereClause[] = "DATE(wf.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
                $types .= "ss";
            }
            break;
    }
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

// Order by latest execution
$sql .= " ORDER BY wf.created_at DESC";

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
$feedbacks = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM workshop_feedback wf";
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
    <title>Magic Of Skills | Workshop Feedback</title>
    <?php include "include/meta.php" ?>
    <style>
        @media (max-width: 767px) {
            .card-header .d-flex {
                flex-direction: column;
            }

            .card-header .d-flex>* {
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
                <h6 class="fw-semibold mb-0">Workshop Feedback</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Workshop Feedback</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div
                    class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <button id="showFilters" class="btn btn-secondary d-md-none mb-3">Show Filters</button>
                        <div id="filterContainer" class="d-none d-md-block">
                            <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                                <span class="text-md fw-medium text-secondary-light mb-0">Show</span>
                                <select name="per_page"
                                    class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px"
                                    onchange="this.form.submit()">
                                    <option value="10" <?php echo $recordsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $recordsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $recordsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $recordsPerPage == 100 ? 'selected' : ''; ?>>100
                                    </option>
                                </select>
                                <input type="text" class="bg-base h-40-px w-auto" name="search"
                                    placeholder="Search (Name, Email, City...)"
                                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                                <select name="workshop_id"
                                    class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px"
                                    onchange="this.form.submit()">
                                    <option value="">All Workshops</option>
                                    <?php foreach ($workshops as $ws): ?>
                                        <option value="<?php echo $ws['id']; ?>" <?php echo isset($_GET['workshop_id']) && $_GET['workshop_id'] == $ws['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ws['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="rating"
                                    class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px"
                                    onchange="this.form.submit()">
                                    <option value="">All Ratings</option>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo isset($_GET['rating']) && $_GET['rating'] == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> Stars
                                        </option>
                                    <?php endfor; ?>
                                </select>

                                <select name="joined"
                                    class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px"
                                    onchange="this.form.submit()">
                                    <option value="">Date</option>
                                    <option value="today" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="yesterday" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                    <option value="this_week" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="this_month" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="custom" <?php echo isset($_GET['joined']) && $_GET['joined'] == 'custom' ? 'selected' : ''; ?>>Custom Dates</option>
                                </select>

                                <?php if (isset($_GET['joined']) && $_GET['joined'] == 'custom'): ?>
                                    <input type="date" name="start_date"
                                        value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>"
                                        class="form-control h-40-px w-auto">
                                    <input type="date" name="end_date"
                                        value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>"
                                        class="form-control h-40-px w-auto">
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary btn-sm h-40-px">Apply Filters</button>
                                <a href="workshop-feedback.php"
                                    class="btn btn-secondary btn-sm h-40-px d-flex align-items-center">Reset</a>
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
                                    <th scope="col">Date</th>
                                    <th scope="col">Workshop</th>
                                    <th scope="col">Participant</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Rating</th>
                                    <th scope="col">Feedback</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($feedbacks) > 0): ?>
                                    <?php foreach ($feedbacks as $index => $row): ?>
                                        <tr>
                                            <td>
                                                <?php echo $offset + $index + 1; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td>
                                                <span class="text-md mb-0 fw-medium text-secondary-light">
                                                    <?php echo htmlspecialchars($row['workshop_title'] ?? 'Unknown Workshop (' . $row['workshop_id'] . ')'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium text-primary-600">
                                                        <?php echo htmlspecialchars($row['full_name']); ?>
                                                    </span>
                                                    <small class="text-secondary-light">
                                                        <?php echo htmlspecialchars($row['email']); ?>
                                                    </small>
                                                    <small class="text-secondary-light">
                                                        <?php echo htmlspecialchars($row['phone']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-secondary-light">
                                                        <?php echo htmlspecialchars($row['school_name']); ?>
                                                    </span>
                                                    <small class="text-secondary-light">
                                                        <?php echo htmlspecialchars($row['city']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="text-lg fw-bold text-warning-main">
                                                        <?php echo $row['rating']; ?>
                                                    </span>
                                                    <iconify-icon icon="solar:star-bold"
                                                        class="text-warning-main"></iconify-icon>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="max-width: 250px;">
                                                    <p class="mb-1 text-sm"><strong>Liked:</strong>
                                                        <?php echo strlen($row['liked_most']) > 50 ? htmlspecialchars(substr($row['liked_most'], 0, 50)) . '...' : htmlspecialchars($row['liked_most']); ?>
                                                    </p>
                                                    <p class="mb-0 text-sm"><strong>Future:</strong>
                                                        <?php echo strlen($row['future_topics']) > 50 ? htmlspecialchars(substr($row['future_topics'], 0, 50)) . '...' : htmlspecialchars($row['future_topics']); ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center gap-2 justify-content-center">
                                                    <!-- Optional: View Details Modal could be added here -->
                                                    <button type="button"
                                                        class="delete-feedback bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                        data-id="<?php echo $row['id']; ?>">
                                                        <iconify-icon icon="fluent:delete-24-regular"
                                                            class="menu-icon"></iconify-icon>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No specific feedback found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-24">
                        <span>Showing
                            <?php echo count($feedbacks) > 0 ? $offset + 1 : 0; ?> to
                            <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of
                            <?php echo $totalRecords; ?> entries
                        </span>
                        <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md"
                                        href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&workshop_id=<?php echo urlencode($_GET['workshop_id'] ?? ''); ?>&rating=<?php echo urlencode($_GET['rating'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
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
                                    <a class="page-link <?php echo $i == $page ? 'bg-primary-600 text-white' : 'bg-neutral-300 text-secondary-light'; ?> fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md"
                                        href="?page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&workshop_id=<?php echo urlencode($_GET['workshop_id'] ?? ''); ?>&rating=<?php echo urlencode($_GET['rating'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link bg-neutral-300 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md"
                                        href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&workshop_id=<?php echo urlencode($_GET['workshop_id'] ?? ''); ?>&rating=<?php echo urlencode($_GET['rating'] ?? ''); ?>&joined=<?php echo urlencode($_GET['joined'] ?? ''); ?>&per_page=<?php echo $recordsPerPage; ?>">
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
    <script src="assets/js/lib/jquery-ui.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Toggle filters on mobile
        document.getElementById('showFilters').addEventListener('click', function () {
            var filterContainer = document.getElementById('filterContainer');
            filterContainer.classList.toggle('d-none');
            filterContainer.classList.toggle('d-block');
        });

        // Excel download functionality
        document.getElementById('downloadExcel').addEventListener('click', function () {
            var table = document.querySelector('table');
            var wb = XLSX.utils.table_to_book(table, { sheet: "Workshop Feedback" });
            XLSX.writeFile(wb, 'workshop_feedback.xlsx');
        });

        // Delete functionality
        $(document).ready(function () {
            $('.delete-feedback').on('click', function () {
                const id = $(this).data('id');

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
                            url: 'delete/workshop-feedback.php',
                            type: 'POST',
                            data: { id: id },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire(
                                        'Deleted!',
                                        response.message,
                                        'success'
                                    ).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire(
                                        'Error!',
                                        response.message,
                                        'error'
                                    );
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error("AJAX Error:", textStatus, errorThrown);
                                Swal.fire(
                                    'Error!',
                                    'An error occurred while deleting. Please check the console.',
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