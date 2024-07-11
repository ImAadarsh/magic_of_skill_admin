<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['workshopId'])) {
    $workshopId = intval($_POST['workshopId']);

    $stmt = $connect->prepare("UPDATE workshops SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $workshopId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Workshop deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete Workshop.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();