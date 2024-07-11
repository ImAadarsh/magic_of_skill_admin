<?php
include 'include/session.php';
include 'include/connect.php';

if (!$connect) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

if (!isset($_GET['cartId'])) {
    die(json_encode(['status' => 'error', 'message' => 'Cart ID is required']));
}

$cartId = intval($_GET['cartId']);

// Fetch cart details
$cartSql = "SELECT c.*, u.first_name, u.last_name, u.email, u.school, u.city 
            FROM carts c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?";

$cartStmt = $connect->prepare($cartSql);
$cartStmt->bind_param("i", $cartId);
$cartStmt->execute();
$cartResult = $cartStmt->get_result();
$cart = $cartResult->fetch_assoc();

if (!$cart) {
    die(json_encode(['status' => 'error', 'message' => 'Cart not found']));
}

// Fetch cart items
$itemsSql = "SELECT i.*, w.name as workshop_name 
             FROM items i
             JOIN workshops w ON i.workshop_id = w.id
             WHERE i.cart_id = ?";

$itemsStmt = $connect->prepare($itemsSql);
$itemsStmt->bind_param("i", $cartId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);

$response = [
    'status' => 'success',
    'customer_name' => $cart['first_name'] . ' ' . $cart['last_name'],
    'email' => $cart['email'],
    'school' => $cart['school'],
    'city' => $cart['city'],
    'total_price' => $cart['price'],
    'coupon_code' => $cart['coupon_code'],
    'discount' => $cart['discount'],
    'final_price' => $cart['price'] - $cart['discount'],
    'payment_status' => $cart['payment_status'],
    'created_at' => $cart['created_at'],
    'items' => $items
];

echo json_encode($response);