<?php
include '../include/session.php';
include '../include/connect.php';
include '../include/quiz-helpers.php';

header('Content-Type: application/json');

if (!$connect) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$fuzzyGroups = buildFuzzySchoolGroups($connect);
$filters = buildQuizStudentScoreFilters($_GET, $fuzzyGroups);

$selectedSchools = parseSelectedSchoolKeys($_GET);

if (empty($selectedSchools) || empty($filters['start_date']) || empty($filters['end_date'])) {
    echo json_encode(['status' => 'error', 'message' => 'At least one school and a date range are required for export.']);
    exit;
}

$sql = "SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.school,
            u.grade,
            u.city,
            u.email,
            COUNT(uqa.attempt_id) AS total_attempts,
            COUNT(DISTINCT uqa.quiz_id) AS quizzes_played,
            COALESCE(SUM(uqa.score), 0) AS cumulative_score,
            COALESCE(AVG(uqa.score), 0) AS average_score,
            COALESCE(MAX(uqa.score), 0) AS best_score
        FROM user_quiz_attempts uqa
        JOIN users u ON uqa.user_id = u.id
        WHERE " . implode(' AND ', $filters['where']) . "
        GROUP BY u.id, u.first_name, u.last_name, u.school, u.grade, u.city, u.email
        ORDER BY cumulative_score DESC, u.first_name ASC, u.last_name ASC";

$stmt = $connect->prepare($sql);
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare export query.']);
    exit;
}

if (!empty($filters['types']) && !empty($filters['params'])) {
    if (!$stmt->bind_param($filters['types'], ...$filters['params'])) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to bind export parameters.']);
        exit;
    }
}

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to execute export query.']);
    exit;
}

$result = $stmt->get_result();
$rows = [];

while ($student = $result->fetch_assoc()) {
    $rows[] = [
        'Rank' => count($rows) + 1,
        'Student Name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'School' => $student['school'],
        'Grade' => $student['grade'] ?? '',
        'City' => $student['city'] ?? '',
        'Email' => $student['email'],
        'Quizzes Played' => (int) $student['quizzes_played'],
        'Total Attempts' => (int) $student['total_attempts'],
        'Cumulative Score' => round((float) $student['cumulative_score'], 2),
        'Average Score' => round((float) $student['average_score'], 2),
        'Best Single Score' => round((float) $student['best_score'], 2),
    ];
}

$stmt->close();
$connect->close();

echo json_encode([
    'status' => 'success',
    'total' => count($rows),
    'students' => $rows,
]);
