<?php
include '../include/session.php';
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contactId'])) {
    $categoryId = intval($_POST['contactId']);

    $stmt = $connect->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $categoryId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Contact deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete Contact.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$connect->close();