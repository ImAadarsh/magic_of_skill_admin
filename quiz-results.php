<?php
include 'include/session.php';
include 'include/connect.php';

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}

// 1. Parse all filter parameters from $_GET
$whereClause = [];
$params = [];
$types = "";

$selectedQuizId = !empty($_GET['quiz_id']) ? $_GET['quiz_id'] : null;
$selectedQuizDate = !empty($_GET['quiz_date']) ? $_GET['quiz_date'] : null;
$selectedSchools = !empty($_GET['school']) ? (is_array($_GET['school']) ? array_filter($_GET['school']) : [$_GET['school']]) : [];
$schoolText = !empty($_GET['school_text']) ? trim($_GET['school_text']) : null;
$selectedGrade = !empty($_GET['grade']) ? $_GET['grade'] : null;
$selectedCity = !empty($_GET['city']) ? $_GET['city'] : null;

// 2. Fetch all quizzes for the filter (independent)
$quizzesSql = "SELECT quiz_id, quiz_name FROM quizzes ORDER BY creation_date DESC";
$quizzesResult = $connect->query($quizzesSql);
$quizzes = $quizzesResult ? $quizzesResult->fetch_all(MYSQLI_ASSOC) : [];

// 3. Fetch all grades for the filter (independent)
$gradesSql = "SELECT DISTINCT grade FROM users WHERE grade IS NOT NULL AND grade != '' ORDER BY grade";
$gradesResult = $connect->query($gradesSql);
$grades = $gradesResult ? $gradesResult->fetch_all(MYSQLI_ASSOC) : [];

// 4. Fetch all cities for the filter with case-insensitive and trimmed normalization (fuzzy logic)
$citiesSql = "SELECT MIN(city) as city, TRIM(LOWER(city)) as normalized_city 
              FROM users 
              WHERE city IS NOT NULL AND city != '' 
              GROUP BY normalized_city 
              ORDER BY city";
$citiesResult = $connect->query($citiesSql);
$cities = $citiesResult ? $citiesResult->fetch_all(MYSQLI_ASSOC) : [];

// 5. Fetch schools dynamically, filtered by OTHER active filters (quiz_id, quiz_date, grade, city)
// This ensures the school list reflects only schools that took the test under these conditions.
$schoolWhere = [];
$schoolParams = [];
$schoolTypes = "";

if ($selectedQuizId) {
    $schoolWhere[] = "uqa.quiz_id = ?";
    $schoolParams[] = $selectedQuizId;
    $schoolTypes .= "i";
}
if ($selectedQuizDate) {
    $schoolWhere[] = "DATE(q.creation_date) = ?";
    $schoolParams[] = $selectedQuizDate;
    $schoolTypes .= "s";
}
if ($selectedGrade) {
    $schoolWhere[] = "u.grade = ?";
    $schoolParams[] = $selectedGrade;
    $schoolTypes .= "s";
}
if ($selectedCity) {
    $schoolWhere[] = "TRIM(LOWER(u.city)) = ?";
    $schoolParams[] = trim(strtolower($selectedCity));
    $schoolTypes .= "s";
}

$schoolsSql = "SELECT MIN(u.school) as school, TRIM(LOWER(u.school)) as normalized_school
               FROM user_quiz_attempts uqa
               JOIN users u ON uqa.user_id = u.id
               JOIN quizzes q ON uqa.quiz_id = q.quiz_id
               WHERE u.school IS NOT NULL AND u.school != ''";

if (!empty($schoolWhere)) {
    $schoolsSql .= " AND " . implode(" AND ", $schoolWhere);
}
$schoolsSql .= " GROUP BY normalized_school ORDER BY school";

$schoolsStmt = $connect->prepare($schoolsSql);
if ($schoolsStmt) {
    if (!empty($schoolTypes) && !empty($schoolParams)) {
        $schoolsStmt->bind_param($schoolTypes, ...$schoolParams);
    }
    $schoolsStmt->execute();
    $schoolsResult = $schoolsStmt->get_result();
    $schools = $schoolsResult->fetch_all(MYSQLI_ASSOC);
} else {
    $schools = [];
}

// 6. Build the WHERE clause for the main user attempts query
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($selectedQuizId) {
        $whereClause[] = "uqa.quiz_id = ?";
        $params[] = $selectedQuizId;
        $types .= "i";
    }
    if ($selectedQuizDate) {
        $whereClause[] = "DATE(q.creation_date) = ?";
        $params[] = $selectedQuizDate;
        $types .= "s";
    }
    if (!empty($selectedSchools)) {
        $placeholders = implode(',', array_fill(0, count($selectedSchools), '?'));
        $whereClause[] = "TRIM(LOWER(u.school)) IN ($placeholders)";
        foreach ($selectedSchools as $sch) {
            $params[] = trim(strtolower($sch));
            $types .= "s";
        }
    }
    if ($schoolText) {
        $whereClause[] = "TRIM(LOWER(u.school)) LIKE ?";
        $params[] = "%" . trim(strtolower($schoolText)) . "%";
        $types .= "s";
    }
    if ($selectedGrade) {
        $whereClause[] = "u.grade = ?";
        $params[] = $selectedGrade;
        $types .= "s";
    }
    if ($selectedCity) {
        $whereClause[] = "TRIM(LOWER(u.city)) = ?";
        $params[] = trim(strtolower($selectedCity));
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
$fullQuizResults = $result->fetch_all(MYSQLI_ASSOC);

// Pre-calculate ranks on the full search results list to keep them consistent across paginated pages
$rank = 1;
$prevScore = null;
$prevTime = null;
foreach ($fullQuizResults as $index => &$res) {
    if ($res['score'] !== $prevScore || $res['time_taken'] !== $prevTime) {
        $rank = $index + 1;
    }
    $res['calculated_rank'] = $rank;
    $prevScore = $res['score'];
    $prevTime = $res['time_taken'];
}
unset($res); // break reference

// Export to CSV if requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=quiz_results.csv');
    $output = fopen('php://output', 'w');
    
    // Output CSV headers
    fputcsv($output, ['Rank', 'Name', 'Quiz', 'School', 'Grade', 'City', 'Score', 'Time Taken']);
    
    foreach ($fullQuizResults as $res) {
        $name = $res['first_name'] . ' ' . $res['last_name'];
        $scoreStr = $res['score'] . ' / ' . $res['total_possible_score'];
        $timeTakenStr = gmdate("H:i:s", $res['time_taken']);
        
        fputcsv($output, [
            $res['calculated_rank'],
            $name,
            $res['quiz_name'],
            $res['school'],
            $res['grade'],
            $res['city'],
            $scoreStr,
            $timeTakenStr
        ]);
    }
    fclose($output);
    exit;
}

// Pagination
$resultsPerPage = 20;
$totalResults = count($fullQuizResults);
$totalPages = ceil($totalResults / $resultsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $resultsPerPage;
$quizResults = array_slice($fullQuizResults, $offset, $resultsPerPage);

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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fa;
        }
        /* Custom Select2 Styles to match Bootstrap 5 */
        .select2-container {
            width: 100% !important;
        }
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 5px;
            min-height: 38px;
            padding: 2px 6px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #0d6efd;
            border: none;
            color: #fff;
            border-radius: 3px;
            padding: 2px 8px;
            margin-top: 4px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 5px;
            background: transparent;
            border: none;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #ffc107;
        }
        /* Custom checkboxes in School select2 dropdown options */
        #select2-school-results .select2-results__option {
            position: relative;
            padding-left: 35px !important;
        }
        #select2-school-results .select2-results__option::before {
            content: "";
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: #fff;
            transition: all 0.15s ease-in-out;
        }
        #select2-school-results .select2-results__option[aria-selected=true]::before {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        #select2-school-results .select2-results__option[aria-selected=true]::after {
            content: "✓";
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            font-size: 11px;
            font-weight: bold;
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
                    <!-- Row 1 -->
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
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <label for="city" class="filter-label">City</label>
                        <select name="city" id="city" class="custom-select">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city['normalized_city']); ?>" <?php echo (isset($_GET['city']) && $_GET['city'] == $city['normalized_city']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Row 2 -->
                    <div class="col-md-4">
                        <label for="school" class="filter-label">School (Select Multiple)</label>
                        <select name="school[]" id="school" class="custom-select" multiple="multiple">
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo htmlspecialchars($school['normalized_school']); ?>" <?php echo (isset($_GET['school']) && (is_array($_GET['school']) ? in_array($school['normalized_school'], $_GET['school']) : $_GET['school'] == $school['normalized_school'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['school']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="school_text" class="filter-label">School (Text Search)</label>
                        <div class="input-group">
                            <input type="text" id="school_text" name="school_text" class="form-control" value="<?php echo isset($_GET['school_text']) ? htmlspecialchars($_GET['school_text']) : ''; ?>" placeholder="Enter school name...">
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="apply-filters-btn me-2 w-50">Apply Filters</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary w-50">Clear Filters</a>
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
                            <?php foreach ($quizResults as $result): ?>
                                <tr>
                                    <td><?php echo $result['calculated_rank']; ?></td>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#quiz_date", {
                dateFormat: "Y-m-d",
                allowInput: true
            });

            $('#school').select2({
                placeholder: "Select schools",
                allowClear: true,
                closeOnSelect: false
            });

            $('.results-table').DataTable({
                "pageLength": 20,
                "order": [[6, "desc"], [7, "asc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [3, 4, 5] }
                ]
            });

            document.getElementById('exportCSV').addEventListener('click', function() {
                let currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('export', 'csv');
                window.location.href = currentUrl.toString();
            });
        });
    </script>
</body>
</html>