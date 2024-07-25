<?php
include 'include/session.php';
include 'include/connect.php';

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch all quizzes for the filter
$quizzesSql = "SELECT quiz_id, quiz_name FROM quizzes ORDER BY creation_date DESC";
$quizzesResult = $connect->query($quizzesSql);
$quizzes = $quizzesResult->fetch_all(MYSQLI_ASSOC);

// Fetch all schools for the filter
$schoolsSql = "SELECT DISTINCT school FROM users WHERE school IS NOT NULL AND school != '' ORDER BY school";
$schoolsResult = $connect->query($schoolsSql);
$schools = $schoolsResult->fetch_all(MYSQLI_ASSOC);

// Fetch all grades for the filter
$gradesSql = "SELECT DISTINCT grade FROM users WHERE grade IS NOT NULL AND grade != '' ORDER BY grade";
$gradesResult = $connect->query($gradesSql);
$grades = $gradesResult->fetch_all(MYSQLI_ASSOC);

// Fetch all cities for the filter
$citiesSql = "SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' ORDER BY city";
$citiesResult = $connect->query($citiesSql);
$cities = $citiesResult->fetch_all(MYSQLI_ASSOC);

// Apply filters and fetch results
$whereClause = [];
$params = [];
$types = "";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['quiz_id'])) {
        $whereClause[] = "uqa.quiz_id = ?";
        $params[] = $_GET['quiz_id'];
        $types .= "i";
    }
    if (!empty($_GET['quiz_date'])) {
        $whereClause[] = "DATE(q.creation_date) = ?";
        $params[] = $_GET['quiz_date'];
        $types .= "s";
    }
    if (!empty($_GET['school'])) {
        $whereClause[] = "u.school = ?";
        $params[] = $_GET['school'];
        $types .= "s";
    }
    if (!empty($_GET['grade'])) {
        $whereClause[] = "u.grade = ?";
        $params[] = $_GET['grade'];
        $types .= "s";
    }
    if (!empty($_GET['city'])) {
        $whereClause[] = "u.city = ?";
        $params[] = $_GET['city'];
        $types .= "s";
    }
}

$sql = "SELECT uqa.*, q.quiz_name, u.first_name, u.last_name, u.school, u.grade, u.city,
               TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time) as time_taken,
               (SELECT SUM(marks) FROM questions WHERE quiz_id = q.quiz_id) as total_possible_score
        FROM user_quiz_attempts uqa
        JOIN quizzes q ON uqa.quiz_id = q.quiz_id
        JOIN users u ON uqa.user_id = u.id";

if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}

$sql .= " ORDER BY uqa.score DESC, time_taken ASC";

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
$quizResults = $result->fetch_all(MYSQLI_ASSOC);

// Pagination
$resultsPerPage = 20;
$totalResults = count($quizResults);
$totalPages = ceil($totalResults / $resultsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $resultsPerPage;
$quizResults = array_slice($quizResults, $offset, $resultsPerPage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - Magic Of Skills</title>
    <?php include "include/meta.php" ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fa;
        }
        .dashboard-main-body {
            padding: 2rem;
        }
        .filters-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .results-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .filter-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .custom-select {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            width: 100%;
        }
        .results-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 15px;
        }
        .results-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        .results-table td {
            background-color: #ffffff;
            padding: 1rem;
            border-top: 1px solid #dee2e6;
        }
        .results-table tr:hover td {
            background-color: #f8f9fa;
            transition: background-color 0.3s ease;
        }
        .score-cell {
            font-weight: 700;
            color: #28a745;
        }
        .time-taken {
            font-weight: 600;
            color: #6c757d;
        }
        .apply-filters-btn {
            background-color: #007bff;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .apply-filters-btn:hover {
            background-color: #0056b3;
        }
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,.05);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.075);
        }
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>

        <div class="dashboard-main-body">
            <h2 class="mb-4">Quiz Results</h2>

            <div class="filters-container">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="quiz_id" class="filter-label">Quiz</label>
                        <select name="quiz_id" id="quiz_id" class="custom-select">
                            <option value="">All Quizzes</option>
                            <?php foreach ($quizzes as $quiz): ?>
                                <option value="<?php echo $quiz['quiz_id']; ?>" <?php echo (isset($_GET['quiz_id']) && $_GET['quiz_id'] == $quiz['quiz_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($quiz['quiz_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="quiz_date" class="filter-label">Quiz Date</label>
                        <input type="text" id="quiz_date" name="quiz_date" class="form-control datepicker" value="<?php echo isset($_GET['quiz_date']) ? htmlspecialchars($_GET['quiz_date']) : ''; ?>" placeholder="Select date">
                    </div>
                    <div class="col-md-4">
                        <label for="school" class="filter-label">School</label>
                        <select name="school" id="school" class="custom-select">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['school']; ?>" <?php echo (isset($_GET['school']) && $_GET['school'] == $school['school']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['school']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="grade" class="filter-label">Grade</label>
                        <select name="grade" id="grade" class="custom-select">
                            <option value="">All Grades</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade['grade']; ?>" <?php echo (isset($_GET['grade']) && $_GET['grade'] == $grade['grade']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="city" class="filter-label">City</label>
                        <select name="city" id="city" class="custom-select">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city['city']; ?>" <?php echo (isset($_GET['city']) && $_GET['city'] == $city['city']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="apply-filters-btn me-2">Apply Filters</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Clear Filters</a>
                    </div>
                </form>
            </div>

            <div class="results-container">
                <button id="exportCSV" class="btn btn-success mb-3">Export to CSV</button>
                <?php if (empty($quizResults)): ?>
                    <p class="no-results">No results found for the selected filters.</p>
                <?php else: ?>
                    <table class="results-table table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Name</th>
                                <th>Quiz</th>
                                <th>School</th>
                                <th>Grade</th>
                                <th>City</th>
                                <th>Score</th>
                                <th>Time Taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            $prevScore = null;
                            $prevTime = null;
                            foreach ($quizResults as $index => $result): 
                                if ($result['score'] !== $prevScore || $result['time_taken'] !== $prevTime) {
                                    $rank = $index + 1;
                                }
                                $prevScore = $result['score'];
                                $prevTime = $result['time_taken'];
                            ?>
                                <tr>
                                    <td><?php echo $rank; ?></td>
                                    <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['quiz_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['school']); ?></td>
                                    <td><?php echo htmlspecialchars($result['grade']); ?></td>
                                    <td><?php echo htmlspecialchars($result['city']); ?></td>
                                    <td class="score-cell"><?php echo $result['score']; ?> / <?php echo $result['total_possible_score']; ?></td>
                                    <td class="time-taken"><?php echo gmdate("H:i:s", $result['time_taken']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <nav aria-label="Quiz results pagination">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#quiz_date", {
                dateFormat: "Y-m-d",
                allowInput: true
            });

            $('.results-table').DataTable({
                "pageLength": 20,
                "order": [[6, "desc"], [7, "asc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [3, 4, 5] }
                ]
            });

            document.getElementById('exportCSV').addEventListener('click', function() {
                let csv = 'Rank,Name,Quiz,School,Grade,City,Score,Time Taken\n';
                document.querySelectorAll('.results-table tbody tr').forEach(function(row) {
                    let rowData = Array.from(row.cells).map(cell => '"' + cell.textContent.trim() + '"');
                    csv += rowData.join(',') + '\n';
                });
                let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                let link = document.createElement("a");
                if (link.download !== undefined) {
                    let url = URL.createObjectURL(blob);
                    link.setAttribute("href", url);
                    link.setAttribute("download", "quiz_results.csv");
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