<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eventId'])) {
    $categoryId = intval($_POST['eventId']);

    $stmt = $connect->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $categoryId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Event deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete Event.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();