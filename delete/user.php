<?php
include '../include/session.php';
include '../include/connect.php';

// Check if the user is an admin
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['userId'])) {
    $userId = $_POST['userId'];

    // Perform the deletion
    $stmt = $connect->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $connect->error]);
        exit;
    }

    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No user found with the given ID']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}