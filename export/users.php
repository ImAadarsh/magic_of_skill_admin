<?php
include '../include/session.php';
include '../include/connect.php';
include '../include/users-filters.php';

header('Content-Type: application/json');

if (!$connect) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$filters = buildUsersFilters($_GET);
$whereClause = $filters['where'];
$params = $filters['params'];
$types = $filters['types'];

$sql = 'SELECT id, email, first_name, last_name, school, city, country_code, mobile, user_type, grade, created_at FROM users';

if (!empty($whereClause)) {
    $sql .= ' WHERE ' . implode(' AND ', $whereClause);
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $connect->prepare($sql);
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare export query.']);
    exit;
}

if (!empty($types) && !empty($params)) {
    if (!$stmt->bind_param($types, ...$params)) {
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

while ($user = $result->fetch_assoc()) {
    $rows[] = [
        'S.L' => count($rows) + 1,
        'Join Date' => date('d M Y', strtotime($user['created_at'])),
        'First Name' => $user['first_name'],
        'Last Name' => $user['last_name'],
        'Email' => $user['email'],
        'School' => $user['school'],
        'Grade' => $user['grade'] ?? '',
        'City' => $user['city'],
        'Country Code' => $user['country_code'],
        'Mobile' => $user['mobile'],
        'User Type' => $user['user_type'] === 'admin' ? 'Admin' : 'User',
    ];
}

$stmt->close();
$connect->close();

echo json_encode([
    'status' => 'success',
    'total' => count($rows),
    'users' => $rows,
]);
