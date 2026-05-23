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
$sql = "SELECT * FROM events";

// Apply filters
$whereClause = [];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $searchTerm = '%' . $_GET['search'] . '%';
    $whereClause[] = "(name LIKE ? OR description LIKE ? OR location LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

// Date filter
if (isset($_GET['date']) && $_GET['date'] != '') {
    $today = date('Y-m-d');
    switch ($_GET['date']) {
        case 'today':
            $whereClause[] = "DATE(date) = ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'upcoming':
            $whereClause[] = "DATE(date) > ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'past':
            $whereClause[] = "DATE(date) < ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'custom':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                $startDate = $_GET['start_date'];
                $endDate = $_GET['end_date'];
                $whereClause[] = "DATE(date) BETWEEN ? AND ?";
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

// Add sorting
$sql .= " ORDER BY date DESC";

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
$events = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of records for pagination
$countSql = "SELECT COUNT(*) FROM events";
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
    <title>Events - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <style>.table-responsive { overflow-x: auto; }</style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Events Database</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">MOS | Events</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="mos-card-header">
                    <div class="mos-card-header-left">
                        <button class="mos-filter-toggle d-lg-none" data-target="eventsFilterBody" aria-expanded="false">
                            <iconify-icon icon="heroicons:funnel" style="font-size:15px"></iconify-icon>
                            <span class="toggle-label">Filters</span>
                            <span class="filter-count-badge">0</span>
                        </button>
                        <div class="mos-filter-body d-lg-block" id="eventsFilterBody">
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
                                    <input type="text" name="search" placeholder="Search events..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="button" class="mos-search-clear">×</button>
                                </div>
                                <select name="date" class="form-select form-select-sm">
                                    <option value="">All Dates</option>
                                    <option value="today" <?php echo isset($_GET['date']) && $_GET['date'] == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="upcoming" <?php echo isset($_GET['date']) && $_GET['date'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="past" <?php echo isset($_GET['date']) && $_GET['date'] == 'past' ? 'selected' : ''; ?>>Past</option>
                                    <option value="custom" <?php echo isset($_GET['date']) && $_GET['date'] == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                                <div style="display:flex;gap:6px;">
                                    <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                                    <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                                </div>
                                <button type="submit" class="mos-btn-apply">
                                    <iconify-icon icon="heroicons:magnifying-glass" style="font-size:13px"></iconify-icon> Apply
                                </button>
                                <a href="events.php" class="mos-btn-reset">
                                    <iconify-icon icon="heroicons:x-mark" style="font-size:13px"></iconify-icon> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                    <div class="mos-card-header-right">
                        <button id="downloadExcel" class="mos-btn-export">
                            <iconify-icon icon="vscode-icons:file-type-excel" style="font-size:16px"></iconify-icon> Export
                        </button>
                        <a href="add-event.php" class="mos-btn-primary">
                            <iconify-icon icon="heroicons:plus" style="font-size:15px"></iconify-icon> Add Event
                        </a>
                    </div>
                </div>
                <div class="mos-active-filters"></div>
                <div class="card-body p-24">
                    <div class="table-responsive mos-table-wrap">
                        <table class="table bordered-table sm-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col" data-sortable>Event Date</th>
                                    <th scope="col" data-sortable>Name</th>
                                    <th scope="col" data-sortable>Location</th>
                                    <th scope="col" data-sortable>Seats</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $index => $event): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td><?php echo date('d M Y', strtotime($event['date'])); ?></td>
                                        <td>
                                            <span class="text-md mb-0 fw-normal text-secondary-light"><?php echo htmlspecialchars($event['name']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td><?php echo $event['seat']; ?></td>
                                        <td class="text-center">
                                            <?php if (strtotime($event['date']) > time()): ?>
                                                <span class="bg-success-focus text-success-600 border border-success-main px-24 py-4 radius-4 fw-medium text-sm">Upcoming</span>
                                            <?php else: ?>
                                                <span class="bg-danger-focus text-danger-600 border border-danger-main px-24 py-4 radius-4 fw-medium text-sm">Past</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"> 
                                            <div class="d-flex align-items-center gap-10 justify-content-center">
                                                <a href="https://magicofskills.com/events-details.php?id=<?php echo htmlspecialchars($event['id']); ?>" type="button" class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"> 
                                                    <iconify-icon icon="majesticons:eye-line" class="icon text-xl"></iconify-icon>
                                                </a>
                                                <a href="edit-event.php?id=<?php echo htmlspecialchars($event['id']); ?>" type="button" class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"> 
                                                    <iconify-icon icon="lucide:edit" class="menu-icon"></iconify-icon>
                                                </a>
                                                <button type="button" class="delete-event bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle" data-eventid="<?php echo $event['id']; ?>"> 
                                                    <iconify-icon icon="fluent:delete-24-regular" class="menu-icon"></iconify-icon>
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
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"><iconify-icon icon="ep:d-arrow-left"></iconify-icon></a></li>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹</a></li>
                            <?php else: ?><li class="disabled"><a>‹</a></li><?php endif; ?>
                            <?php $startPage = max(1, $page - 2); $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="<?php echo $i == $page ? 'active' : ''; ?>"><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">›</a></li>
                                <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><iconify-icon icon="ep:d-arrow-right"></iconify-icon></a></li>
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
            var wb = XLSX.utils.table_to_book(table, {sheet: "Events"});
            XLSX.writeFile(wb, 'events.xlsx');
        });

        // Event deletion functionality
        $(document).ready(function() {
            $('.delete-event').on('click', function() {
                const eventId = $(this).data('eventid');
                
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
                            url: 'delete/event.php',
                            type: 'POST',
                            data: { eventId: eventId },
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
                                console.log("Response Text:", jqXHR.responseText);
                                Swal.fire(
                                    'Error!',
                                    'An error occurred while deleting the event. Please check the console for details.',
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