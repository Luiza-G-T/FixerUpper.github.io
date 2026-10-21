<?php
session_start();
require_once __DIR__ . '/includes/db.php';

//initialise guest cart

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])){
$_SESSION['cart'] = [];
}

//CSRF Token to protect the cartform

if (empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$message = '';

//escape output before displaying in html

function escapeHtml($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

//Handling product quantity update and product removal

if($_SERVER['REQUEST_METHOD'] === 'POST'){
        $submittedToken = $_POST['csrf_token'] ?? '';

        if(
            !is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)    
         ){
            http_response_code(403);
            exit('Invalid request. Please refresh the page and try this again');
         }

         $action = $_POST['action'] ?? '';
         $productId = $_POST['product_id'] ?? '';

         if (!is_string($productId)) {
            $productId = '';
         }

         if($action === 'remove'){
            unset($_SESSION['cart'][$productId]);
         } elseif ($action === 'update') {
            $quantity = filter_var(
                $_POST['quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            if(
                isset($_SESSION['cart'][$productId]) &&
                $quantity !== false &&
                $quantity >= 1 &&
                $quantity <= 99
            ){
                $_SESSION['cart'][$productId] = $quantity;

            } else{
                $message = 'Please choose a quantity between 1 and 99.';
            }   
         }
         //redirect after successful post

         if ($message === '') {
            header('Location: cart.php');
            exit;
         }    
}

//retrieve info from database about products
$cartItems = [];
$total = 0.00;

if(!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $productIds = array_values(array_filter($productIds, 'is_string'));

    if (!empty($productIds)) {
        $placeholders = implode(
            ',',
            array_fill(0,count($productIds), '?')
        );

        $stmt = $pdo->prepare(
            "SELECT id, name, description, price, image FROM products WHERE active = 1 AND id IN ($placeholders)"
        );

        $stmt->execute($productIds);
        $products = $stmt->fetchAll();
        $validCart = [];
        foreach ($products as $product){
            $id = $product['id'];
            $quantity = filter_var(
                $_SESSION['cart'][$id] ?? null,
                FILTER_VALIDATE_INT
            );

            if(
                $quantity === false ||
                $quantity < 1 ||
                $quantity > 99
            ){
                continue;
            }

            $price = (float)$product['price'];
            $product['quantity'] = $quantity;
            $product['subtotal'] = $price * $quantity;

            $cartItems[] = $product;
            $validCart[$id] = $quantity;
            $total += $product['subtotal'];
        }
        //remove deletes products from the list

        $_SESSION['cart'] = $validCart;
    } else {
        $_SESSION['cart'] = [];
    }
}

$itemCount = array_sum($_SESSION['cart']);
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

<!--Cart Content----------------------------------------------------------------------->
 <section id="header" aria-labelledby="Banner_Title">
        <h1 id="Banner_Title">Quality Appliances for Every Home</h1>
        <p id="Banner_Desc">We specialise in a range of hardware appliances, for collection or delivery</p>
        <a href="office.html" id="Shop_Now_Button" class="btn">Shop Now</a>
    </section>

<main class="cart-page">
    <h1>Your Shopping Cart</h1>
    <p class="cart-intro"> Review your items before proceeding to checkout:</p>

<!--PHP Error Message-->

<?php if ($message !== ''): ?>
<p class="cart-message" role="alert">
    <?= escapeHtml($message) ?>
</p>

<?php endif; ?>
<?php if (empty($cartItems)): ?>

<!--Empty Cart--------------------------------->
<section class="cart-empty">
    <i class="fa fa-shopping-cart fa-3x" aria-hidden="true"></i>

    <h2>Your cart is empty</h2>
    <p>You haven't added any products yet</p>
    <a href="kitchen.html" class="checkout-button">Browse our kitchen products</a>
    <a href="office.html" class="checkout-button">Browse our Office products</a>
</section>

<?php else: ?>
    <div class="cart-layout">
        <!--products in the cart-->

        <section class="cart-items" aria-label="Cart items">

            <?php foreach ($cartItems as $item): ?>

            <article class="cart-item">
                <img src="<?= escapeHtml($item['image']) ?>" alt="<?= escapeHtml($item['name']) ?>">
                
                <div class="cart-item-details">
                    <h3><?= escapeHtml($item['name'])?></h3>
                    <p class="description"><?= nl2br(escapeHtml($item['description']))?></p>

                    <!--product price-->
                    <p class="price">£<?= number_format((float)$item['price'], 2)?></p>

                    <!--item subtotal-->

                    <p class="cart-subtotal">Subtotal: <strong>£<?= number_format($item['subtotal'], 2) ?></strong></p>

                    <!--update quantity-->
                    <form action="cart.php" method="post" class="quantity-form">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <input type="hidden" name="product_id" value="<?= escapeHtml($item['id']) ?>">
                        <input type="hidden" name="action" value="update">

                        <label for="quantity-<?= escapeHtml($item['id']) ?>">Quantity</label>
                            <input type="number" id="quantity-<?= escapeHtml($item['id']) ?>"
                            name="quantity"
                            value="<?= (int)$item['quantity'] ?>"
                            min="1"
                            max="99"
                            required>

                            <button type="submit" class="cart-button update-button">Update</button>
                    </form>

                    <!--Removing the prdoduct from cart-->
                    <form action="cart.php" method="post" class="quantity-form">

                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                        <input type="hidden" name="product_id" value="<?= escapeHtml($item['id']) ?>">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="cart-button remove-button"><i class="fa fa-trash" aria-hidden="true"></i>Remove</button>
                    </form>
                </div>
            </article>

            <?php endforeach; ?>
            <a href="kitchen.html" class="continue-shopping">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>Continue Shopping
            </a>
        </section>

        <!--Order Summary--------------------->

        <aside class="cart-summary">
            <h2>Order Summary</h2>
            <div class="summary-line">
                <span>Items</span>
                <span><?= (int)$itemCount ?></span>
            </div>

            <div class="summary-line">
                <span>Subtotal</span>
                <span>
                    £<?= number_format($total, 2) ?>
                </span>
            </div>

            <p>Delivery charges, if applicable, will be confirmed during checkout.</p>

            <div class="summary-line total">
                <span>Total</span>
                <span>
                    £<?= number_format($total, 2) ?>
                </span>
            </div>

            <!--Continue to checkout-->

            <a href="checkout.php" class="checkout-button">Proceed to checkout<i class="fa fa-arrow-right" aria-hidden="true"></i></a>
            <p>You can view and manage you cart without logging in. You will be asked to log in or register at checkout/</p>
        </aside>
    </div>
    <?php endif; ?>
</main>

<!--Footer-->
<footer id="footer">
    <p>&copy; <span id="copyright-year">2026</span> FixerUpper. All rights reserved.</p>
</footer>
</body>
</html>
        