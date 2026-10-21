<?php

session_start();

require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !=='POST') {
        HEADER('Location: index.html');
        exit;
}

$productId = $_POST['product_id'] ?? '';

if (!is_string($productId) || $productId === '') {
    http_response_code(400);
    exit('Invalid product ID. Please try again. ');
}

$stmt = $pdo->prepare(
    'SELECT id FROM products WHERE id = ? AND active = 1'
);
$stmt->execute([$productId]);

$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Product not found. Please check yout product ID in the database. ');
}

if(!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] =[];
}

$currentQuantity = (int)($_SESSION['cart'][$productId] ?? 0);

if($currentQuantity >= 99) {
    header('Location: cart.php');
    exit;
}

$_SESSION['cart'][$productId] = $currentQuantity + 1;

header('Location: cart.php');
exit;

?>