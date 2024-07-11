<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blogId'])) {
    $blogId = intval($_POST['blogId']);

    $stmt = $connect->prepare("UPDATE blogs SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $blogId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Blog deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete blog.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();