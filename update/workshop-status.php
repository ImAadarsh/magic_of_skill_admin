<?php
include '../include/session.php';
include '../include/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['workshopId']) && isset($_POST['status'])) {
    $workshopId = intval($_POST['workshopId']);
    $status = intval($_POST['status']); // 0 for upcoming/ongoing, 1 for completed

    $stmt = $connect->prepare("UPDATE workshops SET is_completed = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $status, $workshopId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Workshop status updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update Workshop status.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();
?>