<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: checkout.php');
    exit;
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$name = $_SESSION['user_name'] ?? '';
$email = $_SESSION['user_email'] ?? '';
$phone = '';
$address = '';
$city = '';
$postcode = '';

$cartItems = [];
$total = 0.00;

$productIds = array_keys($_SESSION['cart']);
$productIds = array_values(array_filter(
    $productIds,
    fn($id) => is_string($id) && $id !== ''
));

if (!$productIds) {
    header('Location: cart.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$stmt = $pdo->prepare(
    "SELECT id, name, description, price, image
     FROM products
     WHERE active = 1 AND id IN ($placeholders)"
);
$stmt->execute($productIds);
$products = $stmt->fetchAll();

$validCart = [];

foreach ($products as $product) {
    $id = (string)$product['id'];
    $quantity = filter_var(
        $_SESSION['cart'][$id] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($quantity === false || $quantity < 1 || $quantity > 99) {
        continue;
    }

    $product['quantity'] = $quantity;
    $product['subtotal'] = (float)$product['price'] * $quantity;

    $cartItems[] = $product;
    $validCart[$id] = $quantity;
    $total += $product['subtotal'];
}

$_SESSION['cart'] = $validCart;

if (!$cartItems) {
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

if (!is_string($token) ||
    !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Invalid request. Please refresh the page.');
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$postcode = trim($_POST['postcode'] ?? '');

if ($name === '' || strlen($name) > 100) {
    $error = 'Please enter your name (maximum 100 characters).';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 255) {
    $error = 'Please enter a valid email address.';
} elseif ($phone === '' || strlen($phone) > 30) {
    $error = 'Please enter a valid phone number.';
} elseif ($address === '' || strlen($address) > 255) {
    $error = 'Please enter your delivery address.';
} elseif ($city === '' || strlen($city) > 100) {
    $error = 'Please enter your city.';
} elseif ($postcode === '' || strlen($postcode) > 20) {
    $error = 'Please enter your postcode.';
} else {
try {
    $pdo->beginTransaction();


    $stmt = $pdo->prepare(
        "SELECT id, name, price
            FROM products
            WHERE active = 1 AND id IN ($placeholders)
            FOR UPDATE"
    );
    $stmt->execute($productIds);
    $currentProducts = $stmt->fetchAll();

    $currentById = [];
    foreach ($currentProducts as $p) {
        $currentById[(string)$p['id']] = $p;
    }

    $orderItems = [];
    $totalCents = 0;

    foreach ($_SESSION['cart'] as $id => $quantity) {
        if (!isset($currentById[(string)$id])) {
            throw new RuntimeException(
                'A product in your cart is no longer available. Please return to your cart.'
            );
        }

        $p = $currentById[(string)$id];
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 1 || $quantity > 99) {
            throw new RuntimeException('A cart quantity is invalid.');
        }

        $unitPriceCents = (int)round((float)$p['price'] * 100);
        $subtotalCents = $unitPriceCents * $quantity;
        $totalCents += $subtotalCents;

        $orderItems[] = [
            'product_id' => $p['id'],
            'product_name' => $p['name'],
            'unit_price' => $unitPriceCents / 100,
            'quantity' => $quantity,
            'subtotal' => $subtotalCents / 100
        ];
    }

    if (!$orderItems) {
        throw new RuntimeException('Your cart is empty.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO orders
            (user_id, email, phone, address, city, postcode, total, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

        $stmt->execute([
        $_SESSION['user_id'],
        $email,
        $phone,
        $address,
        $city,
        $postcode,
        $totalCents / 100,
        'completed'
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO order_items
            (order_id, product_id, product_name, unit_price, quantity, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($orderItems as $item) {
        $stmt->execute([
        $orderId,
        $item['product_id'],
        $item['product_name'],
        $item['unit_price'],
        $item['quantity'],
        $item['subtotal']
        ]);
    }
    $pdo->commit();

    $_SESSION['last_order_id'] = $orderId;
    $_SESSION['cart'] = [];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: order-success.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $error = $e instanceof RuntimeException
        ? $e->getMessage(): 'We could not submit your order. Please try again.';
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<!--Head START-->
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FixerUpper | Quality appliances for Every Home</title>
    <meta name="description" content="Diascover quality home appliances at fixer upper, with convenient collection and delivery options">
    

<!--link to CSS stylesheet-->
    <link rel="stylesheet" href="style.css">

<!--Font awesome Icons-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </head>

<!--Head END--------------------------------------------------------------------------->

<!--Body------------------------------------------------------------------------------->
<body>
<!--Navigation---------------------->
    <header> 
        <nav id="navigation_bar">
            <h1 id="Nav_Title_FixerUpper"><span>FixerUpper</span></h1>  
                <div class="nav-links">
                <a href="index.html" class="btn" aria-current="page">Home</a> 
                <a href="office.html" class="btn">Office</a>
                <a href="kitchen.html" class="btn">Kitchen</a> 
                <a href="cart.php" class="btn"><i class="fa fa-shopping-cart" aria-hidden="true"></i></a>
                <a href="account.html" class="btn"><i class="fa fa-user" aria-hidden="true"></i></a>
        </div>
    </header>

<!--Main Contennt-------------------------------->
<main>

<main class="auth-page">
    <h1>Confirm Your Order</h1>
    <p>Review your order and enter your delivery details.</p>
    <?php if ($error !== ''): ?>
        <p role="alert"><?= h($error) ?></p>
    <?php endif; ?>

    <section>
        <h2>Order Summary</h2>
        <?php foreach ($cartItems as $item): ?>
            <p>
                <?= h($item['name']) ?> × <?= (int)$item['quantity'] ?>
                — £<?= number_format($item['subtotal'], 2) ?>
            </p>
        <?php endforeach; ?>
        <p><strong>Total: £<?= number_format($total, 2) ?></strong></p>
    </section>

    <form method="post" action="confirm-order.php">
        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION['csrf_token']) ?>">

        <label for="name">Full name</label>
         <input id="name" name="name" type="text" maxlength="100"
               autocomplete="name" value="<?= h($name) ?>" required>

        <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="255"
               autocomplete="email" value="<?= h($email) ?>" required>

        <label for="phone">Phone number</label>
            <input id="phone" name="phone" type="tel" maxlength="30"
               autocomplete="tel" value="<?= h($phone) ?>" required>

        <label for="address">Delivery address</label>
        <input id="address" name="address" type="text" maxlength="254"
               autocomplete="street-address" value="<?= h($address) ?>" required>

        <label for="city">Town or city</label>
         <input id="city" name="city" type="text" maxlength="100"
               autocomplete="address-level2" value="<?= h($city) ?>" required>

        <label for="postcode">Postcode</label>
            <input id="postcode" name="postcode" type="text" maxlength="20"
               autocomplete="postal-code" value="<?= h($postcode) ?>" required>

        <button type="submit" class="checkout-button">Confirm and submit order</button>
</form>

    <p><a href="cart.php">Return to cart</a></p>
</main>

<!--Footer-->
<footer id="footer">
    <p>&copy; <span id="copyroght-year">2026</span> FixerUpper. All rights reserved.</p>
</footer>
</body>
</html>
        