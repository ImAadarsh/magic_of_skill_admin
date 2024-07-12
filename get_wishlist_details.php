<?php
include 'include/session.php';
include 'include/connect.php';

if (!isset($_GET['wishlistId'])) {
    echo json_encode(['status' => 'error', 'message' => 'Wishlist ID not provided']);
    exit;
}

$wishlistId = intval($_GET['wishlistId']);

$sql = "SELECT w.*, u.first_name, u.last_name, u.email, u.school, u.city,
               ws.name as workshop_name, ws.price as workshop_price, 
               ws.start_time as workshop_start_date, ws.duration as workshop_duration
        FROM wishlists w
        JOIN users u ON w.user_id = u.id
        JOIN workshops ws ON w.workshop_id = ws.id
        WHERE w.id = ?";

$stmt = $connect->prepare($sql);
$stmt->bind_param("i", $wishlistId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $wishlist = $result->fetch_assoc();
    $response = [
        'status' => 'success',
        'customer_name' => $wishlist['first_name'] . ' ' . $wishlist['last_name'],
        'email' => $wishlist['email'],
        'school' => $wishlist['school'],
        'city' => $wishlist['city'],
        'workshop_name' => $wishlist['workshop_name'],
        'workshop_price' => $wishlist['workshop_price'],
        'workshop_start_date' => date('Y-m-d H:i', strtotime($wishlist['workshop_start_date'])),
        'workshop_duration' => $wishlist['workshop_duration'],
        'created_at' => date('Y-m-d H:i', strtotime($wishlist['created_at']))
    ];
} else {
    $response = ['status' => 'error', 'message' => 'Wishlist not found'];
}

echo json_encode($response);