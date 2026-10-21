<?php

session_start();
require_once __DIR__ . '/includes/db.php';
if (isset($_SESSION['user_id'])) {
    header('Location: checkout.php');
    exit;
}

if (empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error = '';
$email = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
!hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Invalid Request. Please refresh the page.');
    }
}
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password)) {
    $error = 'Invalid email or password';   

} else {
    $stmt = $pdo->prepare ('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash']
    )) {
         session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user ['email'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: checkout.php');
        exit;
    }
    $error = 'Invalid email or password.';
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<!--Head START-->
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FixerUpper | Quality appliances for Every Home
    <meta name="description" content="Diascover quality home appliances at fixer upper, with convenient collection and delivery options">
    </title>

<!--link to CSS stylesheet-->
    <link rel="stylesheet" href="style.css">

<!--Font awesome Icons-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"></head>

<!--Head END--------------------------------------------------------------------------->

<!--Body--------------------------------------------------------------------------------->
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

<!--Main Content-------------------->
<section id="header">
        <h1 id="Banner_Title">Welcome Back</h1>
        <p id="Banner_Desc">Log in to your FixerUpper account to continue with your order.</p>
    </section>

    <main class="auth-page">
        <h1>Customer Login</h1>
        <p>Enter your email address and password below.</p>

        <?php if ($error !== ''): ?>
            <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form action="login.php" method="post">
         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" >
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" autocomplete="email"  value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required >
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
          <button type="submit" class="checkout-button"> <i class="fa fa-sign-in" aria-hidden="true"></i>Log In</button>
        </form>
        <p>Don't have an account?<a href="register.php">Register here</a></p>
        <p><a href="cart.php">Return to your shopping cart</a></p>
    </main>

<!--Footer-->
<footer id="footer">
    <p>&copy; <span id="copyroght-year">2026</span> FixerUpper. All rights reserved.</p>
</footer>
</body>
</html>