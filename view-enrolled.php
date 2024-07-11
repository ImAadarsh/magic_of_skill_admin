<?php
include "include/session.php";
include "include/connect.php";

// Check if workshop ID is provided
if (!isset($_GET['workshop_id']) || empty($_GET['workshop_id'])) {
    header("Location: workshops.php");
    exit();
}

$workshopId = $_GET['workshop_id'];

// Fetch workshop details
$workshopSql = "SELECT name FROM workshops WHERE id = ?";
$workshopStmt = $connect->prepare($workshopSql);
$workshopStmt->bind_param("i", $workshopId);
$workshopStmt->execute();
$workshopResult = $workshopStmt->get_result();

if ($workshopResult->num_rows === 0) {
    header("Location: workshops.php");
    exit();
}

$workshop = $workshopResult->fetch_assoc();

// Pagination settings
$recordsPerPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Base SQL query
$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.mobile, u.school, u.city, 
               p.created_at as enrollment_date, p.amount
        FROM users u
        JOIN payments p ON u.id = p.user_id
        WHERE p.workshop_id = ?";

$countSql = "SELECT COUNT(*) as total FROM users u
             JOIN payments p ON u.id = p.user_id
             WHERE p.workshop_id = ?";

$params = [$workshopId];
$types = "i";

// Apply filters
if (!empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $countSql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $params = array_merge($params, [$search, $search, $search, $search]);
    $types .= "ssss";
}

if (!empty($_GET['school'])) {
    $school = "%{$_GET['school']}%";
    $sql .= " AND u.school LIKE ?";
    $countSql .= " AND u.school LIKE ?";
    $params[] = $school;
    $types .= "s";
}

if (!empty($_GET['city'])) {
    $city = "%{$_GET['city']}%";
    $sql .= " AND u.city LIKE ?";
    $countSql .= " AND u.city LIKE ?";
    $params[] = $city;
    $types .= "s";
}

if (!empty($_GET['start_date'])) {
    $sql .= " AND p.created_at >= ?";
    $countSql .= " AND p.created_at >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if (!empty($_GET['end_date'])) {
    $sql .= " AND p.created_at <= ?";
    $countSql .= " AND p.created_at <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

// Add sorting
$sql .= " ORDER BY p.created_at DESC";

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $recordsPerPage;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the main query
$stmt = $connect->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$enrolledStudents = $result->fetch_all(MYSQLI_ASSOC);

// Prepare and execute the count query
$countStmt = $connect->prepare($countSql);
$countStmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title >Enrolled Students - <?php echo htmlspecialchars($workshop['name']); ?></title>
    <?php include "include/meta.php" ?>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>

        <div class="dashboard-main-body">
            <div class="card basic-data-table radius-12 overflow-hidden">
                <div class="card-body p-24">
                    <h2 style="font-size: 35px !important;" class="mb-20">Enrolled Students - <?php echo htmlspecialchars($workshop['name']); ?></h2>
                    
                    <form method="GET" class="mb-4">
                        <input type="hidden" name="workshop_id" value="<?php echo $workshopId; ?>">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Search name, email, mobile" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control" name="school" placeholder="School" value="<?php echo htmlspecialchars($_GET['school'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo htmlspecialchars($_GET['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="start_date" placeholder="Start Date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="end_date" placeholder="End Date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table bordered-table sm-table mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>School</th>
                                    <th>City</th>
                                    <th>Enrollment Date</th>
                                    <th>Amount Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                                        <td><?php echo htmlspecialchars($student['school']); ?></td>
                                        <td><?php echo htmlspecialchars($student['city']); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($student['enrollment_date'])); ?></td>
                                        <td>₹<?php echo number_format($student['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($enrolledStudents)): ?>
                        <p class="text-center mt-4">No students enrolled yet.</p>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <p>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> entries</p>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?workshop_id=<?php echo $workshopId; ?>&page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['workshop_id' => '', 'page' => ''])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>

                    <div class="mt-4">
                        <button onclick="goBack()" class="btn btn-primary">Back to Workshop</button>
                        <button id="exportExcel" class="btn btn-success">Export to Excel</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        function goBack() {
    window.history.back();
}
        document.getElementById('exportExcel').addEventListener('click', function() {
            var table = document.querySelector('table');
            var wb = XLSX.utils.table_to_book(table, {sheet: "Enrolled Students"});
            XLSX.writeFile(wb, 'enrolled_students_<?php echo $workshopId; ?>.xlsx');
        });
    </script>
</body>
</html>