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
$sql = "SELECT w.*, t.name as trainer_name, c.name as category_name, 
               (SELECT COUNT(DISTINCT p.id) FROM payments p WHERE p.workshop_id = w.id) as enrolled_count
        FROM workshops w 
        LEFT JOIN trainers t ON w.trainer_id = t.id 
        LEFT JOIN categories c ON w.category_id = c.id";



// Apply filters
$whereClause = [];
$params = [];
$types = "";

$whereClause[] = "w.is_deleted = ?";
$params[] = 0;
$types .= "i";
// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(w.name LIKE ? OR w.description LIKE ? OR t.name LIKE ? OR c.name LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

// Category filter
if (isset($_GET['category']) && $_GET['category'] != '') {
    $whereClause[] = "w.category_id = ?";
    $params[] = $_GET['category'];
    $types .= "i";
}

// Trainer filter
if (isset($_GET['trainer']) && $_GET['trainer'] != '') {
    $whereClause[] = "w.trainer_id = ?";
    $params[] = $_GET['trainer'];
    $types .= "i";
}

// Level filter
if (isset($_GET['level']) && $_GET['level'] != '') {
    $whereClause[] = "w.level = ?";
    $params[] = $_GET['level'];
    $types .= "s";
}

// Price range filter
if (isset($_GET['min_price']) && $_GET['min_price'] != '') {
    $whereClause[] = "w.price >= ?";
    $params[] = $_GET['min_price'];
    $types .= "d";
}
if (isset($_GET['max_price']) && $_GET['max_price'] != '') {
    $whereClause[] = "w.price <= ?";
    $params[] = $_GET['max_price'];
    $types .= "d";
}

// Date filter
if (isset($_GET['start_date']) && $_GET['start_date'] != '') {
    $whereClause[] = "w.start_time >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if (isset($_GET['end_date']) && $_GET['end_date'] != '') {
    $whereClause[] = "w.start_time <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

// Status filter
if (isset($_GET['status']) && $_GET['status'] != '') {
    switch ($_GET['status']) {
        case 'completed':
            $whereClause[] = "w.is_completed = 1";
            break;
        case 'upcoming':
            $whereClause[] = "w.is_completed = 0 AND w.start_time > NOW()";
            break;
        case 'ongoing':
            $whereClause[] = "w.is_completed = 0 AND w.start_time <= NOW()";
            break;
    }
}

// Apply where clause
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

// Add sorting
$sql .= " ORDER BY w.start_time DESC";

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
$workshops = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM workshops w 
             LEFT JOIN trainers t ON w.trainer_id = t.id 
             LEFT JOIN categories c ON w.category_id = c.id";
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

// Fetch categories and trainers for filters
$categories = $connect->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$trainers = $connect->query("SELECT * FROM trainers ORDER BY name")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workshops - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <style>
        .table-responsive { overflow-x: auto; }
        .table td iconify-icon {
            pointer-events: none;
        }
    </style>
</head>

<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>

        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Workshops</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Workshops</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <!-- Card Header -->
                <div class="mos-card-header">
                    <div class="mos-card-header-left">
                        <button class="mos-filter-toggle d-lg-none" data-target="workshopsFilterBody" aria-expanded="false">
                            <iconify-icon icon="heroicons:funnel" style="font-size:15px"></iconify-icon>
                            <span class="toggle-label">Filters</span>
                            <span class="filter-count-badge">0</span>
                        </button>
                        <div class="mos-filter-body d-lg-block" id="workshopsFilterBody">
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
                                    <input type="text" name="search" placeholder="Search workshops..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="button" class="mos-search-clear">×</button>
                                </div>
                                <select name="category" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="trainer" class="form-select form-select-sm">
                                    <option value="">All Trainers</option>
                                    <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['id']; ?>" <?php echo isset($_GET['trainer']) && $_GET['trainer'] == $trainer['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($trainer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="level" class="form-select form-select-sm">
                                    <option value="">All Levels</option>
                                    <option value="1" <?php echo isset($_GET['level']) && $_GET['level'] == '1' ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="2" <?php echo isset($_GET['level']) && $_GET['level'] == '2' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="3" <?php echo isset($_GET['level']) && $_GET['level'] == '3' ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="upcoming" <?php echo isset($_GET['status']) && $_GET['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="ongoing" <?php echo isset($_GET['status']) && $_GET['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                </select>
                                <input type="number" name="min_price" placeholder="Min ₹" value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>" style="width:90px">
                                <input type="number" name="max_price" placeholder="Max ₹" value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>" style="width:90px">
                                <div style="display:flex;gap:6px;">
                                    <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                                    <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                                </div>
                                <button type="submit" class="mos-btn-apply">
                                    <iconify-icon icon="heroicons:magnifying-glass" style="font-size:13px"></iconify-icon> Apply
                                </button>
                                <a href="workshops.php" class="mos-btn-reset">
                                    <iconify-icon icon="heroicons:x-mark" style="font-size:13px"></iconify-icon> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                    <div class="mos-card-header-right">
                        <button id="downloadExcel" class="mos-btn-export">
                            <iconify-icon icon="vscode-icons:file-type-excel" style="font-size:16px"></iconify-icon> Export
                        </button>
                        <a href="add-workshop.php" class="mos-btn-primary">
                            <iconify-icon icon="heroicons:plus" style="font-size:15px"></iconify-icon> Add Workshop
                        </a>
                    </div>
                </div>
                <div class="mos-active-filters"></div>
                <div class="mos-table-info-bar">
                    <span>Showing <strong><?php echo $offset + 1; ?></strong> – <strong><?php echo min($offset + $recordsPerPage, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> workshops</span>
                    <span><?php echo $totalPages; ?> page(s)</span>
                </div>
                <div class="card-body p-24">
                    <div class="table-responsive mos-table-wrap">
                        <table class="table bordered-table sm-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col" data-sortable>Name</th>
                                    <th scope="col" data-sortable>Trainer</th>
                                    <th scope="col" data-sortable>Category</th>
                                    <th scope="col" data-sortable>Level</th>
                                    <th scope="col" data-sortable>Price</th>
                                    <th scope="col" data-sortable>Start Time</th>
                                    <th scope="col" data-sortable>Duration</th>
                                    <th scope="col" data-sortable>Enrolled</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workshops as $index => $workshop): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo isset($workshop['icon']) ? $uri . $workshop['icon'] : "assets/images/mos_icon.png"; ?>"
                                                    alt=""
                                                    class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden">
                                                <div class="flex-grow-1">
                                                    <span
                                                        class="text-md mb-0 fw-normal text-secondary-light"><?php echo htmlspecialchars($workshop['name']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($workshop['trainer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($workshop['category_name']); ?></td>
                                        <td><?php echo $workshop['level'] == 1 ? 'Beginner' : ($workshop['level'] == 2 ? 'Intermediate' : 'Advanced'); ?>
                                        </td>
                                        <td><?php echo $workshop['price'] == 0 ? 'Free' : '₹' . number_format($workshop['price'], 2); ?>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($workshop['start_time'])); ?></td>
                                        <td><?php echo $workshop['duration'] . ' mins'; ?></td>
                                        <td>
                                            <?php echo $workshop['enrolled_count']; ?>
                                            <a href="view-enrolled.php?workshop_id=<?php echo $workshop['id']; ?>"
                                                class="btn btn-sm btn-primary ml-2">View</a>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $now = new DateTime();
                                            $start_time = new DateTime($workshop['start_time']);
                                            $end_time = clone $start_time;
                                            $end_time->add(new DateInterval('PT' . $workshop['duration'] . 'M'));
                                            if ($workshop['is_deleted']) {
                                                echo '<span class="bg-danger-focus text-danger-600 border border-danger-main px-24 py-4 radius-4 fw-medium text-sm">Deleted</span>';
                                            } else if ($workshop['is_completed']) {
                                                echo '<span class="bg-success-focus text-success-600 border border-success-main px-24 py-4 radius-4 fw-medium text-sm">Completed</span>';
                                            } elseif ($now < $start_time) {
                                                echo '<span class="bg-info-focus text-info-600 border border-info-main px-24 py-4 radius-4 fw-medium text-sm">Upcoming</span>';
                                            } elseif ($now >= $start_time && $now <= $end_time) {
                                                echo '<span class="bg-warning-focus text-warning-600 border border-warning-main px-24 py-4 radius-4 fw-medium text-sm">Ongoing</span>';
                                            } else {
                                                echo '<span class="bg-danger-focus text-danger-600 border border-danger-main px-24 py-4 radius-4 fw-medium text-sm">Expired</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <a href="https://magicofskills.com/course-details.php?id=<?php echo htmlspecialchars($workshop['id']); ?>"
                                                    type="button"
                                                    class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle">
                                                    <iconify-icon icon="majesticons:eye-line"
                                                        class="icon text-xl"></iconify-icon>
                                                </a>
                                                <a href="edit-workshop.php?id=<?php echo htmlspecialchars($workshop['id']); ?>"
                                                    type="button"
                                                    class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle">
                                                    <iconify-icon icon="lucide:edit" class="menu-icon"></iconify-icon>
                                                </a>
                                                <button type="button"
                                                    class="toggle-status bg-warning-focus bg-hover-warning-200 text-warning-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                    data-workshopid="<?php echo $workshop['id']; ?>"
                                                    data-status="<?php echo $workshop['is_completed'] ? 0 : 1; ?>"
                                                    title="Toggle Completion">
                                                    <iconify-icon
                                                        icon="<?php echo $workshop['is_completed'] ? 'ic:baseline-undo' : 'ic:baseline-check'; ?>"
                                                        class="menu-icon"></iconify-icon>
                                                </button>
                                                <button type="button"
                                                    class="delete-workshop bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                    data-workshopid="<?php echo $workshop['id']; ?>">
                                                    <iconify-icon icon="fluent:delete-24-regular"
                                                        class="menu-icon"></iconify-icon>
                                                </button>
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
                            <?php else: ?>
                                <li class="disabled"><a>‹</a></li>
                            <?php endif; ?>
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">›</a></li>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" title="Last"><iconify-icon icon="ep:d-arrow-right"></iconify-icon></a></li>
                            <?php else: ?>
                                <li class="disabled"><a>›</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

    <script>
        $(document).ready(function () {
            document.getElementById('downloadExcel').addEventListener('click', function () {
                var table = document.querySelector('table');
                var wb = XLSX.utils.table_to_book(table, { sheet: "Workshops" });
                XLSX.writeFile(wb, 'workshops.xlsx');
            });

            // Toggle workshop completion status
            $(document).on('click', '.toggle-status', function () {
                const workshopId = $(this).data('workshopid');
                const status = parseInt($(this).data('status'), 10);
                const statusText = status === 1 ? 'mark as completed' : 'mark as uncompleted';

                Swal.fire({
                    title: 'Change Workshop Status?',
                    text: 'Are you sure you want to ' + statusText + '?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, change it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'update/workshop-status.php',
                            type: 'POST',
                            data: { workshopId: workshopId, status: status },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire('Updated!', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('AJAX Error:', textStatus, errorThrown);
                                console.log('Response Text:', jqXHR.responseText);
                                Swal.fire('Error!', 'An error occurred while updating the status.', 'error');
                            }
                        });
                    }
                });
            });

            // Delete workshop
            $(document).on('click', '.delete-workshop', function () {
                const workshopId = $(this).data('workshopid');

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
                            url: 'delete/workshop.php',
                            type: 'POST',
                            data: { workshopId: workshopId },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire('Deleted!', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('AJAX Error:', textStatus, errorThrown);
                                console.log('Response Text:', jqXHR.responseText);
                                Swal.fire('Error!', 'An error occurred while deleting the workshop.', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>