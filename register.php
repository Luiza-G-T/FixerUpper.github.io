<?php

session_start();
require_once __DIR__ . '/includes/db.php';

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (isset($_SESSION['user_id'])) {
    header('Location: checkout.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if (!is_string($token) ||
!hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Invalid Request. Please refresh the page.');
    }
}

$error = '';
$name = '';
$email = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
!hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Invalid Request. Please refresh the page.');
    }
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($name === '' || strlen($name) > 100) {
    $error = 'Please enter a valid name (maximum 100 characters).';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $error = 'Please enter a valid email.';
} elseif (!is_string($password) || strlen($password) < 8 || strlen($password) > 4096) {
    $error = 'Your password must be at least 8 characters.';
} elseif (!is_string($confirmPassword) || $password !== $confirmPassword) {
    $error = 'Your passwords do not match.';
} else {
    try{
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
         $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: checkout.php');
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = 'An account with this email already exists.';
        } else {
            error_log($e->getMessage());
            $error = 'Registration failes. Please try again.';
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

<!--Main Content-------------------->
<main>
    <!--Hero Banner-->
    <section id="header" aria-labelledby="Banner_Title">
        <h1 id="Banner_Title">Account Registration</h1>
        <p id="Banner_Desc">We specialise in a range of hardware appliances, for collection or delivery</p>
    </section>

    <main class="auth-page">
        <h1>Create an Account</h1>
        <p>Register to continue with your order.</p>

        <?php if ($error !== ''): ?>
        <p role="alert"><?= h($error) ?></p>
        <?php endif; ?>

        <form action="register.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <label for="name">Full Name</label>
        <input id="name" name="name" type="text" maxlength="225" autocomplete="name" value="<?= h($name)?>" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="225" autocomplete="email" value="<?= h($email)?>" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>

        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
        
        <button type="submit">Register and continue</button>
        </form>

        <p>Already have an Account?<a href="login.php">Log In</a></p>
        <p><a href="cart.php">Return to your cart</a></p>
    </main>
<!--Footer-->
<footer id="footer">
    <p>&copy; <span id="copyroght-year">2026</span> FixerUpper. All rights reserved.</p>
</footer>
</body>
</html>
        
</html>
