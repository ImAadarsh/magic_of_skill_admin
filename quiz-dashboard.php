<?php
include "include/connection.php";

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}

// Default to today's date if the filter is not set in the URL parameters
$filterDate = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');

// Build SQL clauses based on date filter
$whereClause = "";
$performanceWhere = "WHERE u.school IS NOT NULL AND u.school != ''";
$params = [];
$types = "";

if ($filterDate != '') {
    $whereClause = "WHERE DATE(start_time) = ?";
    $performanceWhere .= " AND DATE(uqa.start_time) = ?";
    $params[] = $filterDate;
    $types = "s";
}

// 1. Unique users who played on selected date (or all-time)
$todayPlayersSql = "SELECT COUNT(DISTINCT user_id) AS active_today FROM user_quiz_attempts $whereClause";
$stmt = $connect->prepare($todayPlayersSql);
if ($filterDate != '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activeToday = $stmt->get_result()->fetch_assoc()['active_today'] ?? 0;
$stmt->close();

// 2. Total attempts on selected date (or all-time)
$todayAttemptsSql = "SELECT COUNT(*) AS attempts_today FROM user_quiz_attempts $whereClause";
$stmt = $connect->prepare($todayAttemptsSql);
if ($filterDate != '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attemptsToday = $stmt->get_result()->fetch_assoc()['attempts_today'] ?? 0;
$stmt->close();

// 3. Unique schools participating on selected date (or all-time)
$schoolsSql = "SELECT COUNT(DISTINCT u.school) AS participating_schools 
               FROM user_quiz_attempts uqa 
               JOIN users u ON uqa.user_id = u.id 
               " . ($filterDate != '' ? "WHERE DATE(uqa.start_time) = ? AND u.school IS NOT NULL AND u.school != ''" : "WHERE u.school IS NOT NULL AND u.school != ''");
$stmt = $connect->prepare($schoolsSql);
if ($filterDate != '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$participatingSchools = $stmt->get_result()->fetch_assoc()['participating_schools'] ?? 0;
$stmt->close();

// 4. Total unique quiz players (all-time, remains all-time as a global metric)
$totalPlayersSql = "SELECT COUNT(DISTINCT user_id) AS total_players FROM user_quiz_attempts";
$totalPlayersResult = $connect->query($totalPlayersSql);
$totalPlayersRow = $totalPlayersResult->fetch_assoc();
$totalPlayers = $totalPlayersRow['total_players'] ?? 0;

// Fetch school-wise quiz performance on selected date (or all-time)
$performanceSql = "SELECT u.school, 
                          COUNT(DISTINCT uqa.user_id) AS total_students,
                          COUNT(uqa.attempt_id) AS total_attempts,
                          AVG(uqa.score) AS average_score,
                          MAX(uqa.score) AS max_score
                   FROM user_quiz_attempts uqa
                   JOIN users u ON uqa.user_id = u.id
                   $performanceWhere
                   GROUP BY u.school
                   ORDER BY total_students DESC, average_score DESC";
$stmt = $connect->prepare($performanceSql);
if ($filterDate != '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$performanceResult = $stmt->get_result();
$schoolStats = [];
if ($performanceResult) {
    while ($row = $performanceResult->fetch_assoc()) {
        $schoolStats[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quiz Dashboard - Magic Of Skills</title>
  <?php include "include/meta.php"; ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
  <style>
      .stat-icon {
          width: 44-px;
          height: 44-px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 8px;
      }
      .performance-card {
          background-color: #ffffff;
          border-radius: 12px;
          padding: 1.5rem;
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      }
      .filter-card {
          background-color: #ffffff;
          border-radius: 12px;
          padding: 1.25rem 1.5rem;
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
      }
  </style>
</head>
<body>
  <?php include "include/aside.php"; ?>

<main class="dashboard-main">
  <?php include "include/header.php"; ?>

  <div class="dashboard-main-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
      <h6 class="fw-semibold mb-0">Quiz Dashboard</h6>
      <ul class="d-flex align-items-center gap-2">
        <li class="fw-medium">
          <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
            Dashboard
          </a>
        </li>
        <li>-</li>
        <li class="fw-medium">Quiz Analytics</li>
      </ul>
    </div>

    <!-- Date Filter Form -->
    <div class="card filter-card mb-24 border-0">
      <form method="GET" class="row align-items-center gy-2">
        <div class="col-auto">
          <label for="filter_date" class="form-label mb-0 fw-medium text-secondary-light">Filter by Date:</label>
        </div>
        <div class="col-sm-3 col-12">
          <input type="date" id="filter_date" name="filter_date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
        </div>
        <div class="col-auto d-flex gap-2">
          <button type="submit" class="btn btn-primary px-24">Filter</button>
          <a href="quiz-dashboard.php?filter_date=" class="btn btn-outline-secondary px-24">All-Time</a>
        </div>
      </form>
    </div>

    <!-- Stat cards row -->
    <div class="row gy-4 mb-24">
      <!-- Players Today -->
      <div class="col-xxl-3 col-sm-6">
        <div class="card h-100 radius-8 border-0 bg-base p-20 d-flex align-items-center gap-3">
          <div class="stat-icon bg-primary-light text-primary-600">
            <iconify-icon icon="flowbite:users-group-solid" class="icon text-2xl"></iconify-icon>
          </div>
          <div>
            <span class="mb-1 fw-medium text-secondary-light text-sm">
                <?php echo ($filterDate != '') ? 'Quiz Players (' . date('d M Y', strtotime($filterDate)) . ')' : 'Quiz Players (All-Time)'; ?>
            </span>
            <h6 class="fw-semibold text-primary-light mb-0"><?php echo number_format($activeToday); ?></h6>
          </div>
        </div>
      </div>

      <!-- Attempts Today -->
      <div class="col-xxl-3 col-sm-6">
        <div class="card h-100 radius-8 border-0 bg-base p-20 d-flex align-items-center gap-3">
          <div class="stat-icon bg-yellow-light text-yellow">
            <iconify-icon icon="healthicons:i-exam-multiple-choice" class="icon text-2xl"></iconify-icon>
          </div>
          <div>
            <span class="mb-1 fw-medium text-secondary-light text-sm">
                <?php echo ($filterDate != '') ? 'Quiz Attempts (' . date('d M Y', strtotime($filterDate)) . ')' : 'Quiz Attempts (All-Time)'; ?>
            </span>
            <h6 class="fw-semibold text-primary-light mb-0"><?php echo number_format($attemptsToday); ?></h6>
          </div>
        </div>
      </div>

      <!-- Participating Schools -->
      <div class="col-xxl-3 col-sm-6">
        <div class="card h-100 radius-8 border-0 bg-base p-20 d-flex align-items-center gap-3">
          <div class="stat-icon bg-pink-light text-pink">
            <iconify-icon icon="bx:building-house" class="icon text-2xl"></iconify-icon>
          </div>
          <div>
            <span class="mb-1 fw-medium text-secondary-light text-sm">
                <?php echo ($filterDate != '') ? 'Schools Active (' . date('d M Y', strtotime($filterDate)) . ')' : 'Schools Active (All-Time)'; ?>
            </span>
            <h6 class="fw-semibold text-primary-light mb-0"><?php echo number_format($participatingSchools); ?></h6>
          </div>
        </div>
      </div>

      <!-- Total Quiz Players (All-Time) -->
      <div class="col-xxl-3 col-sm-6">
        <div class="card h-100 radius-8 border-0 bg-base p-20 d-flex align-items-center gap-3">
          <div class="stat-icon bg-lilac-light text-lilac">
            <iconify-icon icon="solar:users-group-rounded-bold" class="icon text-2xl"></iconify-icon>
          </div>
          <div>
            <span class="mb-1 fw-medium text-secondary-light text-sm">Total Quiz Players (All-Time)</span>
            <h6 class="fw-semibold text-primary-light mb-0"><?php echo number_format($totalPlayers); ?></h6>
          </div>
        </div>
      </div>
    </div>

    <!-- School Performance Statistics -->
    <div class="row">
      <div class="col-12">
        <div class="performance-card border-0">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-20">
            <h5 class="fw-bold mb-0">
                School-wise Quiz Performance
                <small class="text-muted text-sm fw-normal ms-2">
                    (<?php echo ($filterDate != '') ? 'Data for ' . date('d M Y', strtotime($filterDate)) : 'All-Time Cumulative Data'; ?>)
                </small>
            </h5>
            <button id="exportPerformanceCSV" class="btn btn-success btn-sm">Export to CSV</button>
          </div>

          <div class="table-responsive">
            <table id="schoolPerformanceTable" class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th>S.L</th>
                  <th>School Name</th>
                  <th class="text-center">Participating Students</th>
                  <th class="text-center">Total Quiz Attempts</th>
                  <th class="text-center">Average Score</th>
                  <th class="text-center">Highest Score</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($schoolStats as $index => $stat): ?>
                  <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                      <span class="text-md mb-0 fw-semibold text-secondary-light"><?php echo htmlspecialchars($stat['school']); ?></span>
                    </td>
                    <td class="text-center fw-medium"><?php echo $stat['total_students']; ?></td>
                    <td class="text-center fw-medium"><?php echo $stat['total_attempts']; ?></td>
                    <td class="text-center fw-bold text-success-main"><?php echo number_format($stat['average_score'], 2); ?></td>
                    <td class="text-center fw-bold text-primary-light"><?php echo number_format($stat['max_score'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include "include/footer.php"; ?>
</main>

<?php include "include/script.php"; ?>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
<script>
    $(document).ready(function() {
        $('#schoolPerformanceTable').DataTable({
            "pageLength": 10,
            "order": [[2, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [0] }
            ]
        });

        document.getElementById('exportPerformanceCSV').addEventListener('click', function() {
            let csv = 'S.L,School Name,Participating Students,Total Quiz Attempts,Average Score,Highest Score\n';
            $('#schoolPerformanceTable').DataTable().rows().data().each(function(row, index) {
                // Strip HTML tags for clean CSV data
                let schoolName = $(row[1]).text().trim() || row[1];
                let rowData = [
                    index + 1,
                    '"' + schoolName + '"',
                    row[2],
                    row[3],
                    row[4],
                    row[5]
                ];
                csv += rowData.join(',') + '\n';
            });
            let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement("a");
            if (link.download !== undefined) {
                let url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", "school_quiz_performance.csv");
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });
    });
</script>
</body>
</html>
