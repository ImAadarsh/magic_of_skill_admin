<?php
include 'include/session.php';
include 'include/connect.php';

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}

// 1. Dynamic User Details API endpoint (Ajax)
if (isset($_GET['action']) && $_GET['action'] === 'get_user_details' && !empty($_GET['user_id'])) {
    header('Content-Type: application/json');
    $userId = intval($_GET['user_id']);
    
    $userSql = "SELECT first_name, last_name, email, mobile, city, school, grade, icon FROM users WHERE id = ?";
    $userStmt = $connect->prepare($userSql);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();
    
    $statsSql = "SELECT COUNT(*) as total_attempts, AVG(score) as avg_score, MIN(start_time) as first_attempt 
                 FROM user_quiz_attempts 
                 WHERE user_id = ?";
    $statsStmt = $connect->prepare($statsSql);
    $statsStmt->bind_param("i", $userId);
    $statsStmt->execute();
    $statsData = $statsStmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'user' => $userData,
        'stats' => [
            'total_attempts' => intval($statsData['total_attempts']),
            'avg_score' => round(floatval($statsData['avg_score']), 2),
            'first_attempt' => $statsData['first_attempt'] ? date('j M Y, h:i A', strtotime($statsData['first_attempt'])) : 'N/A'
        ],
        'avatar' => (!empty($userData['icon'])) ? 'https://api.magicofskills.com/storage/app/' . $userData['icon'] : 'assets/images/mos_icon.png'
    ]);
    exit;
}

// 2. Bulk Delete API endpoint (Post)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && !empty($_POST['attempt_ids'])) {
    header('Content-Type: application/json');
    $attemptIds = array_map('intval', $_POST['attempt_ids']);
    if (!empty($attemptIds)) {
        $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
        $deleteSql = "DELETE FROM user_quiz_attempts WHERE attempt_id IN ($placeholders)";
        $deleteStmt = $connect->prepare($deleteSql);
        $types = str_repeat('i', count($attemptIds));
        $deleteStmt->bind_param($types, ...$attemptIds);
        if ($deleteStmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => count($attemptIds) . ' attempts deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete selected attempts.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No attempts selected.']);
    }
    exit;
}

// 3. Parse Advanced Filter Parameters
$whereClause = [];
$params = [];
$types = "";

$selectedQuizId = !empty($_GET['quiz_id']) ? $_GET['quiz_id'] : null;
$selectedQuizDateRange = !empty($_GET['quiz_date_range']) ? $_GET['quiz_date_range'] : null;
$selectedSchools = !empty($_GET['school']) ? (is_array($_GET['school']) ? array_filter($_GET['school']) : [$_GET['school']]) : [];
$schoolText = !empty($_GET['school_text']) ? trim($_GET['school_text']) : null;
$selectedGrade = !empty($_GET['grade']) ? $_GET['grade'] : null;
$selectedCity = !empty($_GET['city']) ? $_GET['city'] : null;

// Advanced specific filters
$scoreMin = isset($_GET['score_min']) && $_GET['score_min'] !== '' ? intval($_GET['score_min']) : null;
$scoreMax = isset($_GET['score_max']) && $_GET['score_max'] !== '' ? intval($_GET['score_max']) : null;
$timeMin = isset($_GET['time_min']) && $_GET['time_min'] !== '' ? intval($_GET['time_min']) : null;
$timeMax = isset($_GET['time_max']) && $_GET['time_max'] !== '' ? intval($_GET['time_max']) : null;
$passStatus = !empty($_GET['pass_status']) ? $_GET['pass_status'] : null;
$resultsPerPage = isset($_GET['page_size']) ? intval($_GET['page_size']) : 20;
if (!in_array($resultsPerPage, [10, 20, 50, 100])) {
    $resultsPerPage = 20;
}

// Parse Flatpickr date range
$startDate = null;
$endDate = null;
if ($selectedQuizDateRange && strpos($selectedQuizDateRange, ' to ') !== false) {
    list($startDate, $endDate) = explode(' to ', $selectedQuizDateRange);
} else if ($selectedQuizDateRange) {
    $startDate = $selectedQuizDateRange;
    $endDate = $selectedQuizDateRange;
}

// 4. Fetch filter lists (Quizzes, Grades, Normalized Cities)
$quizzesSql = "SELECT quiz_id, quiz_name FROM quizzes ORDER BY creation_date DESC";
$quizzesResult = $connect->query($quizzesSql);
$quizzes = $quizzesResult ? $quizzesResult->fetch_all(MYSQLI_ASSOC) : [];

$gradesSql = "SELECT DISTINCT grade FROM users WHERE grade IS NOT NULL AND grade != '' ORDER BY grade";
$gradesResult = $connect->query($gradesSql);
$grades = $gradesResult ? $gradesResult->fetch_all(MYSQLI_ASSOC) : [];

$citiesSql = "SELECT MIN(city) as city, TRIM(LOWER(city)) as normalized_city 
              FROM users 
              WHERE city IS NOT NULL AND city != '' 
              GROUP BY normalized_city 
              ORDER BY city";
$citiesResult = $connect->query($citiesSql);
$cities = $citiesResult ? $citiesResult->fetch_all(MYSQLI_ASSOC) : [];

// 5. Fetch Schools List dynamically (dependent on date/quiz/grade/city filter)
$schoolWhere = [];
$schoolParams = [];
$schoolTypes = "";

if ($selectedQuizId) {
    $schoolWhere[] = "uqa.quiz_id = ?";
    $schoolParams[] = $selectedQuizId;
    $schoolTypes .= "i";
}
if ($startDate && $endDate) {
    $schoolWhere[] = "DATE(q.creation_date) BETWEEN ? AND ?";
    $schoolParams[] = $startDate;
    $schoolParams[] = $endDate;
    $schoolTypes .= "ss";
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

// 6. Build the WHERE clause for attempts query
if ($selectedQuizId) {
    $whereClause[] = "uqa.quiz_id = ?";
    $params[] = $selectedQuizId;
    $types .= "i";
}
if ($startDate && $endDate) {
    $whereClause[] = "DATE(q.creation_date) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
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
if ($scoreMin !== null) {
    $whereClause[] = "uqa.score >= ?";
    $params[] = $scoreMin;
    $types .= "i";
}
if ($scoreMax !== null) {
    $whereClause[] = "uqa.score <= ?";
    $params[] = $scoreMax;
    $types .= "i";
}
if ($timeMin !== null) {
    $whereClause[] = "IF(q.duration_minutes > 0, LEAST(TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time), q.duration_minutes * 60), TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time)) >= ?";
    $params[] = $timeMin;
    $types .= "i";
}
if ($timeMax !== null) {
    $whereClause[] = "IF(q.duration_minutes > 0, LEAST(TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time), q.duration_minutes * 60), TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time)) <= ?";
    $params[] = $timeMax;
    $types .= "i";
}
if ($passStatus === 'passed') {
    $whereClause[] = "uqa.score >= ((SELECT SUM(marks) FROM questions WHERE quiz_id = q.quiz_id) / 2)";
} else if ($passStatus === 'failed') {
    $whereClause[] = "uqa.score < ((SELECT SUM(marks) FROM questions WHERE quiz_id = q.quiz_id) / 2)";
}

$sql = "SELECT uqa.*, q.quiz_name, u.first_name, u.last_name, u.school, u.grade, u.city,
               IF(q.duration_minutes > 0, LEAST(TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time), q.duration_minutes * 60), TIMESTAMPDIFF(SECOND, uqa.start_time, uqa.end_time)) as time_taken,
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
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
$fullQuizResults = $result->fetch_all(MYSQLI_ASSOC);

// 7. Calculate Ranks, KPIs, Chart Stats
$rank = 1;
$prevScore = null;
$prevTime = null;
$totalAttempts = count($fullQuizResults);
$totalScore = 0;
$totalPossibleScoreSum = 0;
$passedAttempts = 0;
$totalDuration = 0;

$scoreDistribution = [];
$attemptsTimeline = [];
$schoolPerformance = [];
$gradeBreakdown = [];

foreach ($fullQuizResults as $index => &$res) {
    if ($res['score'] !== $prevScore || $res['time_taken'] !== $prevTime) {
        $rank = $index + 1;
    }
    $res['calculated_rank'] = $rank;
    $prevScore = $res['score'];
    $prevTime = $res['time_taken'];
    
    // KPIs sums
    $totalScore += $res['score'];
    $totalPossibleScoreSum += $res['total_possible_score'];
    if ($res['total_possible_score'] > 0 && ($res['score'] / $res['total_possible_score']) >= 0.5) {
        $passedAttempts++;
    }
    $totalDuration += $res['time_taken'];

    // Charts aggregation
    $scoreKey = intval($res['score']);
    $scoreDistribution[$scoreKey] = ($scoreDistribution[$scoreKey] ?? 0) + 1;
    
    $dateKey = date('Y-m-d', strtotime($res['start_time']));
    $attemptsTimeline[$dateKey] = ($attemptsTimeline[$dateKey] ?? 0) + 1;
    
    $schName = !empty($res['school']) ? $res['school'] : 'No School';
    if (!isset($schoolPerformance[$schName])) {
        $schoolPerformance[$schName] = ['total_score' => 0, 'count' => 0];
    }
    $schoolPerformance[$schName]['total_score'] += $res['score'];
    $schoolPerformance[$schName]['count']++;
    
    $gradeName = !empty($res['grade']) ? $res['grade'] : 'Unknown';
    $gradeBreakdown[$gradeName] = ($gradeBreakdown[$gradeName] ?? 0) + 1;
}
unset($res); // break reference

// Sort/format chart variables
ksort($scoreDistribution);
ksort($attemptsTimeline);
$schoolAvg = [];
foreach ($schoolPerformance as $name => $data) {
    $schoolAvg[$name] = round($data['total_score'] / $data['count'], 2);
}
arsort($schoolAvg);
$schoolAvg = array_slice($schoolAvg, 0, 8); // Top 8 schools

$avgScore = $totalAttempts > 0 ? round(($totalScore / $totalAttempts), 2) : 0;
$avgPossible = $totalAttempts > 0 ? round(($totalPossibleScoreSum / $totalAttempts), 2) : 0;
$avgScorePercent = $avgPossible > 0 ? round(($avgScore / $avgPossible) * 100, 1) : 0;
$passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0;
$avgDuration = $totalAttempts > 0 ? round($totalDuration / $totalAttempts) : 0;
$avgDurationStr = gmdate("i:s", $avgDuration);

$topPerformers = array_slice($fullQuizResults, 0, 3);

// 8. CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=quiz_results.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Rank', 'Name', 'Quiz', 'School', 'Grade', 'City', 'Score', 'Time Taken']);
    
    // Support exporting only selected row IDs if provided
    $exportResults = $fullQuizResults;
    if (!empty($_GET['ids'])) {
        $idsToExport = array_map('intval', explode(',', $_GET['ids']));
        $exportResults = array_filter($fullQuizResults, function($r) use ($idsToExport) {
            return in_array($r['attempt_id'], $idsToExport);
        });
    }
    
    foreach ($exportResults as $res) {
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

// 9. Pagination
$totalPages = ceil($totalAttempts / $resultsPerPage);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            font-size: 0.85rem;
        }
        
        /* Glassmorphism theme cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            box-shadow: 0 4px 16px 0 rgba(31, 38, 135, 0.02);
            border: 1px solid rgba(226, 232, 240, 0.8);
            margin-bottom: 0.85rem;
            transition: all 0.2s ease;
        }
        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(31, 38, 135, 0.04);
        }
        
        /* Auto Refresh progress bar */
        .refresh-progress-container {
            height: 3px;
            width: 100%;
            background-color: #e2e8f0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
        }
        .refresh-progress-bar {
            height: 100%;
            width: 0%;
            background-color: #0d6efd;
            transition: width 1s linear;
        }
        
        /* KPIs summary style */
        .kpi-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0.25rem 0.5rem;
        }
        .kpi-icon {
            font-size: 1.35rem;
            padding: 8px;
            border-radius: 8px;
            background-color: rgba(13, 110, 253, 0.08);
            color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
        }
        .kpi-value {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #0f172a;
            line-height: 1.2;
        }
        .kpi-title {
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-top: 2px;
        }
        
        /* Top 3 Podium layout */
        .podium-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            min-height: 125px;
            padding: 5px 0;
            gap: 10px;
        }
        .podium-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 32%;
            position: relative;
        }
        .podium-rank {
            width: 100%;
            text-align: center;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
            padding: 3px 2px;
            color: white;
            font-weight: 700;
            font-size: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .podium-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 5px;
            border: 2px solid;
            font-size: 0.8rem;
        }
        .step-1 { height: 65px; background: linear-gradient(135deg, #ffd700, #ffae00); }
        .avatar-1 { border-color: #ffd700; color: #ffae00; background-color: #fffbeb; }
        .step-2 { height: 48px; background: linear-gradient(135deg, #c0c0c0, #9ca3af); }
        .avatar-2 { border-color: #c0c0c0; color: #4b5563; background-color: #f3f4f6; }
        .step-3 { height: 35px; background: linear-gradient(135deg, #cd7f32, #b45309); }
        .avatar-3 { border-color: #cd7f32; color: #b45309; background-color: #fff7ed; }
        
        .podium-name {
            font-size: 0.72rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            color: #334155;
        }
        .podium-score {
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
        }
        
        /* Rank Medal Badges */
        .medal-badge {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.72rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }
        .medal-1 { background-color: #ffd700; color: #7f6a00; }
        .medal-2 { background-color: #c0c0c0; color: #444; }
        .medal-3 { background-color: #cd7f32; color: #fff; }
        .medal-default { background-color: #f1f5f9; color: #475569; }
        
        /* Results Table formatting */
        .results-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 4px;
        }
        .results-table th {
            background-color: #f8fafc;
            border: none;
            font-weight: 600;
            color: #475569;
            padding: 8px 10px;
            font-size: 0.78rem;
        }
        .results-table td {
            background-color: #ffffff;
            border: none;
            padding: 8px 10px;
            vertical-align: middle;
            border-top: 1px solid #e2e8f0;
            font-size: 0.8rem;
        }
        .results-table tbody tr {
            box-shadow: 0 1px 2px rgba(0,0,0,0.01);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .results-table tbody tr:hover {
            transform: scale(1.002);
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .score-cell {
            font-weight: 700;
            color: #16a34a;
        }
        .time-taken {
            font-weight: 600;
            color: #64748b;
        }
        .no-results {
            text-align: center;
            padding: 1.5rem;
            color: #64748b;
            font-style: italic;
        }
        
        /* Custom Select2 Overrides */
        .select2-container {
            width: 100% !important;
        }
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            min-height: 32px;
            padding: 1px 4px;
            font-size: 0.8rem;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #3b82f6;
            outline: 0;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #0d6efd;
            border: none;
            color: #fff;
            border-radius: 4px;
            padding: 1px 6px;
            margin-top: 2px;
            font-size: 0.72rem;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 4px;
            background: transparent;
            border: none;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #f59e0b;
        }
        
        /* Custom checkboxes in School select2 dropdown options */
        .select2-multiple-checkboxes .select2-results__option {
            position: relative;
            padding-left: 30px !important;
            font-size: 0.8rem;
        }
        .select2-multiple-checkboxes .select2-results__option::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            background-color: #fff;
            transition: all 0.1s ease-in-out;
        }
        .select2-multiple-checkboxes .select2-results__option--selected::before {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }
        .select2-multiple-checkboxes .select2-results__option--selected::after {
            content: "✓" !important;
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff !important;
            font-size: 10px;
            font-weight: bold;
            display: block !important;
        }
        .select2-container--default .select2-results__option--selected {
            display: block !important;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            display: block !important;
        }
        
        /* Compact Form Controls */
        .form-control, .form-select, .custom-select {
            height: 32px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            box-shadow: none !important;
        }
        .form-control:focus, .form-select:focus, .custom-select:focus {
            border-color: #3b82f6 !important;
        }
        .filter-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 2px;
            display: block;
        }
        
        /* Visual view switch grid layout */
        .grid-view-container {
            display: none;
        }
        .grid-student-card {
            background: #fff;
            border-radius: 8px;
            padding: 0.85rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            height: 100%;
            transition: transform 0.15s, box-shadow 0.15s;
            position: relative;
        }
        .grid-student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.04);
        }
        .grid-student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            margin-right: 8px;
        }
        .apply-filters-btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Print Report Optimized layout */
        @media print {
            body { background: white; color: black; }
            .dashboard-main-body { padding: 0; }
            .glass-card, .results-container, .filters-container {
                box-shadow: none !important;
                border: none !important;
                background: none !important;
                padding: 0 !important;
                margin-bottom: 2rem !important;
            }
            aside, header, footer, .apply-filters-btn, .btn, #exportCSV, .refresh-progress-container, nav, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter {
                display: none !important;
            }
            .results-table td, .results-table th {
                border: 1px solid #dee2e6 !important;
            }
        }
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>

        <div class="refresh-progress-container"><div id="refreshProgressBar" class="refresh-progress-bar"></div></div>

        <div class="dashboard-main-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-0 fw-bold text-dark fs-5">Quiz Results Analytics</h4>
                    <p class="text-muted mb-0" style="font-size: 0.72rem;">Executive dashboard for quiz performance tracking</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="form-check form-switch bg-white px-2 py-1 rounded border mb-0 d-flex align-items-center" style="height: 32px; font-size: 0.8rem; padding-left: 2.25rem;">
                        <input class="form-check-input" type="checkbox" id="autoRefreshSwitch" style="cursor: pointer;">
                        <label class="form-check-label text-dark fw-semibold ms-1" for="autoRefreshSwitch" style="cursor: pointer; user-select: none;">Live Update (30s)</label>
                    </div>
                    <button class="btn btn-outline-secondary bg-white text-dark border btn-sm d-inline-flex align-items-center" id="printReport" style="height: 32px; font-size: 0.8rem; padding: 0 10px;">
                        <iconify-icon icon="solar:printer-minimalistic-linear" class="align-middle me-1" style="font-size: 1rem;"></iconify-icon> Print Report
                    </button>
                    <div class="btn-group border btn-group-sm" role="group" style="height: 32px;">
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center" id="viewModeTable" title="Table View" style="padding: 0 10px; font-size: 0.95rem;">
                            <iconify-icon icon="solar:list-linear"></iconify-icon>
                        </button>
                        <button type="button" class="btn btn-light bg-white text-dark d-inline-flex align-items-center" id="viewModeGrid" title="Card Grid View" style="padding: 0 10px; font-size: 0.95rem;">
                            <iconify-icon icon="solar:widget-linear"></iconify-icon>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Top Row: Podium & KPIs -->
            <div class="row">
                <!-- Top Performers Podium -->
                <div class="col-lg-5 col-md-12">
                    <div class="glass-card h-100">
                        <h5 class="fw-bold mb-3 text-dark d-flex align-items-center">
                            <iconify-icon icon="solar:cup-bold" class="text-warning me-2"></iconify-icon> Top 3 Performers
                        </h5>
                        <?php if (empty($topPerformers)): ?>
                            <p class="text-center text-muted py-5">No attempts to rank.</p>
                        <?php else: ?>
                            <div class="podium-wrapper">
                                <!-- 2nd Place -->
                                <?php if (isset($topPerformers[1])): 
                                    $p2 = $topPerformers[1];
                                    $initials = strtoupper(substr($p2['first_name'], 0, 1) . substr($p2['last_name'], 0, 1));
                                ?>
                                    <div class="podium-step">
                                        <div class="podium-avatar avatar-2" title="<?php echo htmlspecialchars($p2['first_name'] . ' ' . $p2['last_name']); ?>">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="podium-name"><?php echo htmlspecialchars($p2['first_name'] . ' ' . $p2['last_name']); ?></div>
                                        <div class="podium-score"><?php echo $p2['score']; ?> pts</div>
                                        <div class="podium-rank step-2">2nd</div>
                                    </div>
                                <?php endif; ?>

                                <!-- 1st Place -->
                                <?php if (isset($topPerformers[0])): 
                                    $p1 = $topPerformers[0];
                                    $initials = strtoupper(substr($p1['first_name'], 0, 1) . substr($p1['last_name'], 0, 1));
                                ?>
                                    <div class="podium-step">
                                        <div class="podium-avatar avatar-1" title="<?php echo htmlspecialchars($p1['first_name'] . ' ' . $p1['last_name']); ?>">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="podium-name"><?php echo htmlspecialchars($p1['first_name'] . ' ' . $p1['last_name']); ?></div>
                                        <div class="podium-score"><?php echo $p1['score']; ?> pts</div>
                                        <div class="podium-rank step-1">1st</div>
                                    </div>
                                <?php endif; ?>

                                <!-- 3rd Place -->
                                <?php if (isset($topPerformers[2])): 
                                    $p3 = $topPerformers[2];
                                    $initials = strtoupper(substr($p3['first_name'], 0, 1) . substr($p3['last_name'], 0, 1));
                                ?>
                                    <div class="podium-step">
                                        <div class="podium-avatar avatar-3" title="<?php echo htmlspecialchars($p3['first_name'] . ' ' . $p3['last_name']); ?>">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="podium-name"><?php echo htmlspecialchars($p3['first_name'] . ' ' . $p3['last_name']); ?></div>
                                        <div class="podium-score"><?php echo $p3['score']; ?> pts</div>
                                        <div class="podium-rank step-3">3rd</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KPI Cards summary grid -->
                <div class="col-lg-7 col-md-12">
                    <div class="row h-100">
                        <div class="col-md-6 mb-3">
                            <div class="glass-card kpi-card">
                                <div>
                                    <h4 class="kpi-value"><?php echo number_format($totalAttempts); ?></h4>
                                    <span class="kpi-title">Total Attempts</span>
                                </div>
                                <div class="kpi-icon"><iconify-icon icon="solar:notes-linear"></iconify-icon></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="glass-card kpi-card">
                                <div>
                                    <h4 class="kpi-value"><?php echo $avgScore; ?> / <?php echo $avgPossible; ?> <span class="fs-6 fw-normal text-muted">(<?php echo $avgScorePercent; ?>%)</span></h4>
                                    <span class="kpi-title">Avg Score</span>
                                </div>
                                <div class="kpi-icon"><iconify-icon icon="solar:star-linear"></iconify-icon></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="glass-card kpi-card">
                                <div>
                                    <h4 class="kpi-value"><?php echo $passRate; ?>%</h4>
                                    <span class="kpi-title">Passing Rate</span>
                                </div>
                                <div class="kpi-icon"><iconify-icon icon="solar:checklist-linear"></iconify-icon></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="glass-card kpi-card">
                                <div>
                                    <h4 class="kpi-value"><?php echo $avgDurationStr; ?></h4>
                                    <span class="kpi-title">Avg Duration</span>
                                </div>
                                <div class="kpi-icon"><iconify-icon icon="solar:clock-circle-linear"></iconify-icon></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabbed Analytical Charts -->
            <div class="glass-card">
                <ul class="nav nav-pills mb-3 border-bottom pb-2" id="chartsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="timeline-tab" data-bs-toggle="pill" data-bs-target="#tab-timeline" type="button" role="tab">Attempts Over Time</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="score-dist-tab" data-bs-toggle="pill" data-bs-target="#tab-score-dist" type="button" role="tab">Score Distribution</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="schools-tab" data-bs-toggle="pill" data-bs-target="#tab-schools" type="button" role="tab">Top Schools Performance</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="grades-tab" data-bs-toggle="pill" data-bs-target="#tab-grades" type="button" role="tab">Grade Breakdown</button>
                    </li>
                </ul>
                <div class="tab-content" id="chartsTabContent">
                    <div class="tab-pane fade show active" id="tab-timeline" role="tabpanel">
                        <div id="chartTimeline" style="height: 250px;"></div>
                    </div>
                    <div class="tab-pane fade" id="tab-score-dist" role="tabpanel">
                        <div id="chartScoreDistribution" style="height: 250px;"></div>
                    </div>
                    <div class="tab-pane fade" id="tab-schools" role="tabpanel">
                        <div id="chartSchools" style="height: 250px;"></div>
                    </div>
                    <div class="tab-pane fade" id="tab-grades" role="tabpanel">
                        <div id="chartGrades" style="height: 250px;"></div>
                    </div>
                </div>
            </div>

            <!-- Filters Toggle and Form -->
            <button class="btn btn-outline-primary shadow-sm border mb-3 w-100 d-flex justify-content-between align-items-center bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFiltersPanel">
                <span class="fw-semibold"><iconify-icon icon="solar:filter-linear" class="align-middle me-1 text-primary"></iconify-icon> Toggle Advanced Filters</span>
                <iconify-icon icon="solar:alt-arrow-down-linear"></iconify-icon>
            </button>
            
            <div class="collapse <?php echo (!empty($whereClause)) ? 'show' : ''; ?> mb-3" id="advancedFiltersPanel">
                <div class="filters-container border shadow-sm rounded p-4 bg-white">
                    <form method="GET" class="row g-3">
                        <!-- Row 1 -->
                        <div class="col-md-4">
                            <label for="quiz_id" class="filter-label">Quiz</label>
                            <select name="quiz_id" id="quiz_id" class="custom-select">
                                <option value="">All Quizzes</option>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <option value="<?php echo $quiz['quiz_id']; ?>" <?php echo ($selectedQuizId == $quiz['quiz_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quiz['quiz_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="quiz_date_range" class="filter-label">Quiz Date Range</label>
                            <input type="text" id="quiz_date_range" name="quiz_date_range" class="form-control datepicker" value="<?php echo htmlspecialchars($selectedQuizDateRange); ?>" placeholder="Select date range">
                        </div>
                        <div class="col-md-2">
                            <label for="grade" class="filter-label">Grade</label>
                            <select name="grade" id="grade" class="custom-select">
                                <option value="">All Grades</option>
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo $grade['grade']; ?>" <?php echo ($selectedGrade == $grade['grade']) ? 'selected' : ''; ?>>
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
                                    <option value="<?php echo htmlspecialchars($city['normalized_city']); ?>" <?php echo ($selectedCity == $city['normalized_city']) ? 'selected' : ''; ?>>
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
                                    <option value="<?php echo htmlspecialchars($school['normalized_school']); ?>" <?php echo (in_array($school['normalized_school'], $selectedSchools)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['school']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="school_text" class="filter-label">School (Text Search)</label>
                            <input type="text" id="school_text" name="school_text" class="form-control" value="<?php echo htmlspecialchars($schoolText); ?>" placeholder="Enter school name...">
                        </div>
                        <div class="col-md-2">
                            <label for="pass_status" class="filter-label">Status</label>
                            <select name="pass_status" id="pass_status" class="custom-select">
                                <option value="">All Statuses</option>
                                <option value="passed" <?php echo ($passStatus === 'passed') ? 'selected' : ''; ?>>Passed (>=50%)</option>
                                <option value="failed" <?php echo ($passStatus === 'failed') ? 'selected' : ''; ?>>Failed (<50%)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="page_size" class="filter-label">Per Page</label>
                            <select name="page_size" id="page_size" class="custom-select">
                                <option value="10" <?php echo ($resultsPerPage == 10) ? 'selected' : ''; ?>>10 entries</option>
                                <option value="20" <?php echo ($resultsPerPage == 20) ? 'selected' : ''; ?>>20 entries</option>
                                <option value="50" <?php echo ($resultsPerPage == 50) ? 'selected' : ''; ?>>50 entries</option>
                                <option value="100" <?php echo ($resultsPerPage == 100) ? 'selected' : ''; ?>>100 entries</option>
                            </select>
                        </div>

                        <!-- Advanced Filtering Fields Row 3 -->
                        <div class="col-md-3">
                            <label class="filter-label">Min Score</label>
                            <input type="number" name="score_min" class="form-control" value="<?php echo $scoreMin !== null ? $scoreMin : ''; ?>" placeholder="Min score">
                        </div>
                        <div class="col-md-3">
                            <label class="filter-label">Max Score</label>
                            <input type="number" name="score_max" class="form-control" value="<?php echo $scoreMax !== null ? $scoreMax : ''; ?>" placeholder="Max score">
                        </div>
                        <div class="col-md-3">
                            <label class="filter-label">Min Duration (Secs)</label>
                            <input type="number" name="time_min" class="form-control" value="<?php echo $timeMin !== null ? $timeMin : ''; ?>" placeholder="Min secs">
                        </div>
                        <div class="col-md-3">
                            <label class="filter-label">Max Duration (Secs)</label>
                            <input type="number" name="time_max" class="form-control" value="<?php echo $timeMax !== null ? $timeMax : ''; ?>" placeholder="Max secs">
                        </div>

                        <div class="col-12 d-flex justify-content-end mt-3 gap-2">
                            <button type="submit" class="btn btn-primary px-4"><iconify-icon icon="solar:magnifer-linear" class="align-middle me-1"></iconify-icon> Search / Filter</button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary px-4">Clear All</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk actions floating panel -->
            <div id="bulkActionsPanel" class="alert alert-primary bg-white shadow border border-primary d-none align-items-center justify-content-between p-3 rounded mb-3">
                <div class="d-flex align-items-center">
                    <iconify-icon icon="solar:check-square-bold" class="text-primary fs-4 me-2"></iconify-icon>
                    <span id="bulkSelectedCount" class="fw-semibold text-dark">0 attempts selected</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-success d-flex align-items-center" id="bulkExportCSV">
                        <iconify-icon icon="solar:download-linear" class="me-1"></iconify-icon> Export Selected
                    </button>
                    <button class="btn btn-sm btn-danger d-flex align-items-center" id="bulkDelete">
                        <iconify-icon icon="solar:trash-bin-trash-linear" class="me-1"></iconify-icon> Delete Selected
                    </button>
                </div>
            </div>

            <!-- Results container -->
            <div class="results-container glass-card">
                <button id="exportCSV" class="btn btn-success mb-3 shadow-sm">
                    <iconify-icon icon="solar:download-linear" class="align-middle me-1"></iconify-icon> Export All Matching to CSV
                </button>
                
                <?php if (empty($quizResults)): ?>
                    <p class="no-results py-4">No results found for the selected filters.</p>
                <?php else: ?>
                    <!-- List View Table -->
                    <div id="tableViewWrapper">
                        <table class="results-table table table-hover">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" id="selectAllCheckbox" class="form-check-input"></th>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Quiz</th>
                                    <th>School</th>
                                    <th>Grade</th>
                                    <th>City</th>
                                    <th width="150">Score</th>
                                    <th>Time Taken</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizResults as $result): ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input row-checkbox" data-id="<?php echo $result['attempt_id']; ?>"></td>
                                        <td>
                                            <?php 
                                            $rank = $result['calculated_rank'];
                                            if ($rank == 1) echo '<span class="medal-badge medal-1">1</span>';
                                            else if ($rank == 2) echo '<span class="medal-badge medal-2">2</span>';
                                            else if ($rank == 3) echo '<span class="medal-badge medal-3">3</span>';
                                            else echo '<span class="medal-badge medal-default">' . $rank . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0);" class="text-primary fw-semibold view-student-details" data-id="<?php echo $result['user_id']; ?>">
                                                <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['quiz_name']); ?></td>
                                        <td><?php echo htmlspecialchars($result['school']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($result['grade']); ?></span></td>
                                        <td><?php echo htmlspecialchars($result['city']); ?></td>
                                        <td>
                                            <span class="score-cell"><?php echo $result['score']; ?> / <?php echo $result['total_possible_score']; ?></span>
                                            <?php 
                                            $ratio = $result['total_possible_score'] > 0 ? ($result['score'] / $result['total_possible_score']) * 100 : 0;
                                            $barColor = $ratio >= 75 ? 'bg-success' : ($ratio >= 50 ? 'bg-warning' : 'bg-danger');
                                            ?>
                                            <div class="progress mt-1" style="height: 6px; width: 100px;">
                                                <div class="progress-bar <?php echo $barColor; ?>" style="width: <?php echo $ratio; ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="time-taken"><iconify-icon icon="solar:clock-circle-linear" class="align-middle me-1 text-muted"></iconify-icon> <?php echo gmdate("H:i:s", $result['time_taken']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Card Grid View Container -->
                    <div id="gridViewWrapper" class="grid-view-container row g-3">
                        <?php foreach ($quizResults as $result): 
                            $initials = strtoupper(substr($result['first_name'], 0, 1) . substr($result['last_name'], 0, 1));
                            $ratio = $result['total_possible_score'] > 0 ? ($result['score'] / $result['total_possible_score']) * 100 : 0;
                            $cardBadgeColor = $ratio >= 75 ? 'bg-success' : ($ratio >= 50 ? 'bg-warning' : 'bg-danger');
                        ?>
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="grid-student-card">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="grid-student-avatar"><?php echo $initials; ?></div>
                                        <div style="min-width: 0;">
                                            <h6 class="mb-0 fw-bold text-dark text-truncate">
                                                <a href="javascript:void(0);" class="view-student-details" data-id="<?php echo $result['user_id']; ?>">
                                                    <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted d-block text-truncate"><?php echo htmlspecialchars($result['school']); ?></small>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge <?php echo $cardBadgeColor; ?> px-2 py-1 fs-7">
                                            Score: <?php echo $result['score']; ?> / <?php echo $result['total_possible_score']; ?>
                                        </span>
                                        <span class="badge bg-light text-dark px-2 py-1 fs-7">
                                            Grade <?php echo htmlspecialchars($result['grade']); ?>
                                        </span>
                                    </div>
                                    <div class="fs-8 text-muted mt-2 border-top pt-2 d-flex justify-content-between">
                                        <span>Rank #<?php echo $result['calculated_rank']; ?></span>
                                        <span>Time: <?php echo gmdate("i:s", $result['time_taken']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Quiz results pagination" class="mt-4">
                        <ul class="pagination justify-content-center mb-0">
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

        <!-- Student details Modal -->
        <div class="modal fade" id="studentDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow border-0">
                    <div class="modal-header border-0 bg-primary text-white p-4">
                        <div class="d-flex align-items-center">
                            <img id="modalAvatar" src="" class="rounded-circle border border-white" width="60" height="60" style="object-fit: cover;" onerror="this.src='assets/images/mos_icon.png'">
                            <div class="ms-3">
                                <h5 class="modal-title fw-bold mb-0" id="modalStudentName">Loading Name...</h5>
                                <small id="modalStudentSchool" class="opacity-75">Loading School...</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-light">
                        <div class="row g-3">
                            <div class="col-12 border-bottom pb-2 mb-2">
                                <h6 class="fw-bold text-primary mb-3">Contact & Demographics</h6>
                                <div class="row">
                                    <div class="col-6 mb-2">
                                        <small class="text-muted d-block">Email Address</small>
                                        <span id="modalStudentEmail" class="fw-medium text-dark">Loading...</span>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted d-block">Mobile Number</small>
                                        <span id="modalStudentMobile" class="fw-medium text-dark">Loading...</span>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted d-block">Grade / Class</small>
                                        <span id="modalStudentGrade" class="fw-medium text-dark">Loading...</span>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted d-block">City / Region</small>
                                        <span id="modalStudentCity" class="fw-medium text-dark">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <h6 class="fw-bold text-primary mb-3">Quiz Attempt Summary</h6>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="bg-white p-3 rounded text-center border">
                                            <h4 class="fw-bold text-dark mb-0" id="modalAttemptsCount">0</h4>
                                            <small class="text-muted">Total Quizzes</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-3 rounded text-center border">
                                            <h4 class="fw-bold text-success mb-0" id="modalAvgScore">0.0</h4>
                                            <small class="text-muted">Avg Score</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-3 rounded text-center border overflow-hidden">
                                            <small class="text-muted d-block">First Test</small>
                                            <span class="fw-bold text-dark fs-8" id="modalFirstAttempt">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
    <script>
        // Pre-populated PHP data for ApexCharts
        const timelineData = <?php echo json_encode($attemptsTimeline); ?>;
        const scoreDistData = <?php echo json_encode($scoreDistribution); ?>;
        const schoolAvgData = <?php echo json_encode($schoolAvg); ?>;
        const gradeBreakdownData = <?php echo json_encode($gradeBreakdown); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // 1. Flatpickr initialization
            flatpickr("#quiz_date_range", {
                mode: "range",
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // 2. Select2 Multiple Dropdown
            $('#school').select2({
                placeholder: "Select schools",
                allowClear: true,
                closeOnSelect: false,
                dropdownCssClass: 'select2-multiple-checkboxes'
            });

            // 3. DataTable Formatting
            $('.results-table').DataTable({
                "paging": false,
                "searching": false,
                "lengthChange": false,
                "info": false,
                "order": [[1, "asc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [0] }
                ]
            });

            // 4. View Mode Switcher
            const tableViewBtn = document.getElementById('viewModeTable');
            const gridViewBtn = document.getElementById('viewModeGrid');
            const tableView = document.getElementById('tableViewWrapper');
            const gridView = document.getElementById('gridViewWrapper');

            function setViewMode(mode) {
                if (mode === 'grid') {
                    if (tableView) tableView.style.display = 'none';
                    if (gridView) {
                        gridView.style.display = 'flex';
                        gridView.classList.remove('grid-view-container');
                    }
                    gridViewBtn.classList.remove('btn-light', 'bg-white', 'text-dark');
                    gridViewBtn.classList.add('btn-primary');
                    tableViewBtn.classList.remove('btn-primary');
                    tableViewBtn.classList.add('btn-light', 'bg-white', 'text-dark');
                    localStorage.setItem('quiz_view_mode', 'grid');
                } else {
                    if (tableView) tableView.style.display = 'block';
                    if (gridView) gridView.style.display = 'none';
                    tableViewBtn.classList.remove('btn-light', 'bg-white', 'text-dark');
                    tableViewBtn.classList.add('btn-primary');
                    gridViewBtn.classList.remove('btn-primary');
                    gridViewBtn.classList.add('btn-light', 'bg-white', 'text-dark');
                    localStorage.setItem('quiz_view_mode', 'table');
                }
            }

            if (tableViewBtn && gridViewBtn) {
                tableViewBtn.addEventListener('click', () => setViewMode('table'));
                gridViewBtn.addEventListener('click', () => setViewMode('grid'));

                // Restore view mode state
                const savedViewMode = localStorage.getItem('quiz_view_mode') || 'table';
                setViewMode(savedViewMode);
            }

            // 5. Auto-Refresh Loop
            let refreshInterval;
            let refreshTimeLeft = 30;
            const refreshSwitch = document.getElementById('autoRefreshSwitch');
            const progressBar = document.getElementById('refreshProgressBar');

            function startAutoRefresh() {
                refreshTimeLeft = 30;
                if (progressBar) {
                    progressBar.style.width = '0%';
                    progressBar.style.transition = 'none';
                    progressBar.offsetHeight; // trigger reflow
                    progressBar.style.transition = 'width 30s linear';
                    progressBar.style.width = '100%';
                }

                clearInterval(refreshInterval);
                refreshInterval = setInterval(() => {
                    refreshTimeLeft--;
                    if (refreshTimeLeft <= 0) {
                        clearInterval(refreshInterval);
                        location.reload();
                    }
                }, 1000);
            }

            function stopAutoRefresh() {
                clearInterval(refreshInterval);
                if (progressBar) {
                    progressBar.style.transition = 'none';
                    progressBar.style.width = '0%';
                }
            }

            if (refreshSwitch) {
                // Restore state
                if (localStorage.getItem('quiz_auto_refresh') === 'true') {
                    refreshSwitch.checked = true;
                    startAutoRefresh();
                }

                refreshSwitch.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('quiz_auto_refresh', 'true');
                        startAutoRefresh();
                    } else {
                        localStorage.setItem('quiz_auto_refresh', 'false');
                        stopAutoRefresh();
                    }
                });
            }

            // 6. ApexCharts rendering
            const timelineKeys = Object.keys(timelineData || {});
            const timelineValues = Object.values(timelineData || {});
            if (document.querySelector("#chartTimeline")) {
                const timelineOptions = {
                    series: [{
                        name: 'Attempts',
                        data: timelineValues
                    }],
                    chart: {
                        type: 'area',
                        height: 250,
                        toolbar: { show: false }
                    },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.4,
                            opacityTo: 0.1,
                            stops: [0, 90, 100]
                        }
                    },
                    colors: ['#0d6efd'],
                    xaxis: {
                        categories: timelineKeys,
                        labels: { show: true, style: { colors: '#6c757d', fontFamily: 'Poppins' } }
                    },
                    yaxis: {
                        labels: { style: { colors: '#6c757d', fontFamily: 'Poppins' } }
                    },
                    dataLabels: { enabled: false },
                    tooltip: { theme: 'light' }
                };
                const timelineChart = new ApexCharts(document.querySelector("#chartTimeline"), timelineOptions);
                timelineChart.render();
            }

            const scoreKeys = Object.keys(scoreDistData || {});
            const scoreValues = Object.values(scoreDistData || {}).map(Number);
            if (document.querySelector("#chartScoreDistribution")) {
                const scoreDistOptions = {
                    series: [{
                        name: 'Students',
                        data: scoreValues
                    }],
                    chart: {
                        type: 'bar',
                        height: 250,
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 4,
                            horizontal: false,
                            columnWidth: '55%',
                        }
                    },
                    dataLabels: { enabled: false },
                    colors: ['#198754'],
                    xaxis: {
                        categories: scoreKeys,
                        title: { text: 'Score Obtained', style: { color: '#6c757d', fontFamily: 'Poppins', fontWeight: 500 } },
                        labels: { style: { colors: '#6c757d', fontFamily: 'Poppins' } }
                    },
                    yaxis: {
                        title: { text: 'Number of Students', style: { color: '#6c757d', fontFamily: 'Poppins', fontWeight: 500 } },
                        labels: { style: { colors: '#6c757d', fontFamily: 'Poppins' } }
                    },
                    tooltip: { theme: 'light' }
                };
                const scoreDistChart = new ApexCharts(document.querySelector("#chartScoreDistribution"), scoreDistOptions);
                scoreDistChart.render();
            }

            const schoolKeys = Object.keys(schoolAvgData || {});
            const schoolValues = Object.values(schoolAvgData || {}).map(Number);
            if (document.querySelector("#chartSchools")) {
                const schoolsOptions = {
                    series: [{
                        name: 'Average Score',
                        data: schoolValues
                    }],
                    chart: {
                        type: 'bar',
                        height: 250,
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 4,
                            horizontal: true,
                            barHeight: '70%',
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val) { return val + " pts"; },
                        style: { colors: ['#fff'], fontFamily: 'Poppins', fontSize: '11px' }
                    },
                    colors: ['#fd7e14'],
                    xaxis: {
                        categories: schoolKeys,
                        labels: { style: { colors: '#6c757d', fontFamily: 'Poppins' } }
                    },
                    yaxis: {
                        labels: { style: { colors: '#6c757d', fontFamily: 'Poppins' } }
                    },
                    tooltip: { theme: 'light' }
                };
                const schoolsChart = new ApexCharts(document.querySelector("#chartSchools"), schoolsOptions);
                schoolsChart.render();
            }

            const gradeKeys = Object.keys(gradeBreakdownData || {});
            const gradeValues = Object.values(gradeBreakdownData || {}).map(Number);
            if (document.querySelector("#chartGrades")) {
                const gradesOptions = {
                    series: gradeValues,
                    labels: gradeKeys,
                    chart: {
                        type: 'donut',
                        height: 250
                    },
                    colors: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'],
                    legend: {
                        position: 'right',
                        fontFamily: 'Poppins',
                        labels: { colors: '#495057' }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val, opts) {
                            return opts.w.config.series[opts.seriesIndex];
                        }
                    },
                    tooltip: { theme: 'light' }
                };
                const gradesChart = new ApexCharts(document.querySelector("#chartGrades"), gradesOptions);
                gradesChart.render();
            }

            // Trigger window resize when changing tabs to prevent ApexCharts rendering issues inside hidden divs
            document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(button => {
                button.addEventListener('shown.bs.tab', function() {
                    window.dispatchEvent(new Event('resize'));
                });
            });

            // 7. AJAX User Details Modal
            $(document).on('click', '.view-student-details', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                
                $('#modalStudentName').text('Loading...');
                $('#modalStudentSchool').text('Loading...');
                $('#modalStudentEmail').text('Loading...');
                $('#modalStudentMobile').text('Loading...');
                $('#modalStudentGrade').text('Loading...');
                $('#modalStudentCity').text('Loading...');
                $('#modalAttemptsCount').text('-');
                $('#modalAvgScore').text('-');
                $('#modalFirstAttempt').text('Loading...');
                $('#modalAvatar').attr('src', 'assets/images/mos_icon.png');
                
                const detailsModal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
                detailsModal.show();
                
                $.ajax({
                    url: 'quiz-results.php',
                    type: 'GET',
                    data: {
                        action: 'get_user_details',
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.user) {
                            const fullName = (response.user.first_name || '') + ' ' + (response.user.last_name || '');
                            $('#modalStudentName').text(fullName.trim() || 'N/A');
                            $('#modalStudentSchool').text(response.user.school || 'N/A');
                            $('#modalStudentEmail').text(response.user.email || 'N/A');
                            $('#modalStudentMobile').text(response.user.mobile || 'N/A');
                            $('#modalStudentGrade').text(response.user.grade || 'N/A');
                            $('#modalStudentCity').text(response.user.city || 'N/A');
                            $('#modalAttemptsCount').text(response.stats.total_attempts);
                            $('#modalAvgScore').text(response.stats.avg_score);
                            $('#modalFirstAttempt').text(response.stats.first_attempt);
                            $('#modalAvatar').attr('src', response.avatar);
                        } else {
                            $('#modalStudentName').text('Failed to load user details');
                        }
                    },
                    error: function() {
                        $('#modalStudentName').text('Error loading details');
                    }
                });
            });

            // 8. Bulk Selection & Productivity Panel
            const selectAll = document.getElementById('selectAllCheckbox');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const bulkPanel = document.getElementById('bulkActionsPanel');
            const bulkCountText = document.getElementById('bulkSelectedCount');

            function getSelectedAttemptIds() {
                const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                const ids = [];
                checkedCheckboxes.forEach(cb => {
                    ids.push(cb.getAttribute('data-id'));
                });
                return ids;
            }

            function updateBulkPanelState() {
                const selectedIds = getSelectedAttemptIds();
                if (selectedIds.length > 0) {
                    if (bulkCountText) bulkCountText.textContent = `${selectedIds.length} attempt(s) selected`;
                    if (bulkPanel) {
                        bulkPanel.classList.remove('d-none');
                        bulkPanel.classList.add('d-flex');
                    }
                } else {
                    if (bulkPanel) {
                        bulkPanel.classList.remove('d-flex');
                        bulkPanel.classList.add('d-none');
                    }
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    rowCheckboxes.forEach(cb => {
                        cb.checked = selectAll.checked;
                    });
                    updateBulkPanelState();
                });
            }

            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (!this.checked && selectAll) {
                        selectAll.checked = false;
                    }
                    const allChecked = Array.from(rowCheckboxes).every(c => c.checked);
                    if (allChecked && selectAll) {
                        selectAll.checked = true;
                    }
                    updateBulkPanelState();
                });
            });

            // Bulk CSV Export
            const bulkExportCSVBtn = document.getElementById('bulkExportCSV');
            if (bulkExportCSVBtn) {
                bulkExportCSVBtn.addEventListener('click', function() {
                    const ids = getSelectedAttemptIds();
                    if (ids.length > 0) {
                        let currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('export', 'csv');
                        currentUrl.searchParams.set('ids', ids.join(','));
                        window.location.href = currentUrl.toString();
                    }
                });
            }

            // Bulk Delete AJAX
            const bulkDeleteBtn = document.getElementById('bulkDelete');
            if (bulkDeleteBtn) {
                bulkDeleteBtn.addEventListener('click', function() {
                    const ids = getSelectedAttemptIds();
                    if (ids.length > 0) {
                        if (confirm(`Are you sure you want to delete ${ids.length} selected attempt(s)? This action cannot be undone.`)) {
                            $.ajax({
                                url: 'quiz-results.php',
                                type: 'POST',
                                data: {
                                    action: 'bulk_delete',
                                    attempt_ids: ids
                                },
                                success: function(response) {
                                    if (response.status === 'success') {
                                        alert(response.message);
                                        location.reload();
                                    } else {
                                        alert(response.message || 'Failed to delete attempts.');
                                    }
                                },
                                error: function() {
                                    alert('An error occurred while deleting attempts.');
                                }
                            });
                        }
                    }
                });
            }

            // 9. CSV Export (All Matching Results)
            const exportCSVBtn = document.getElementById('exportCSV');
            if (exportCSVBtn) {
                exportCSVBtn.addEventListener('click', function() {
                    let currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('export', 'csv');
                    currentUrl.searchParams.delete('ids'); // Ensure we export all matching
                    window.location.href = currentUrl.toString();
                });
            }

            // 10. Print Report Button
            const printBtn = document.getElementById('printReport');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    window.print();
                });
            }
        });
    </script>
</body>
</html>