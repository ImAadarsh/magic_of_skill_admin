<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trainerId'])) {
    $trainerId = intval($_POST['trainerId']);

    $stmt = $connect->prepare("DELETE FROM trainers WHERE id = ?");
    $stmt->bind_param("i", $trainerId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Trainer deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete Trainer.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();