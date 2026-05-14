<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/mailer.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../checkout.php?message=Please login or register to place an order');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['place_order'])) {
    header('Location: ../checkout.php');
    exit;
}

csrf_check();

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: ../cart.php');
    exit;
}

// ---- Validate user input --------------------------------------------------
$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$phone   = trim((string)($_POST['phone']   ?? ''));
$city    = trim((string)($_POST['city']    ?? ''));
$address = trim((string)($_POST['address'] ?? ''));

if ($name === '' || mb_strlen($name) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254
    || $phone === '' || mb_strlen($phone) > 32
    || $city === ''  || mb_strlen($city) > 100
    || $address === '' || mb_strlen($address) > 255) {
    header('Location: ../checkout.php?message=Please fill in all fields correctly.');
    exit;
}

// ---- Re-fetch each cart line from DB (no trust of session prices) ---------
$line_items = [];
$totalQty   = 0;

foreach ($_SESSION['cart'] as $product_id => $cart_row) {
    $pid = (int)$product_id;
    $qty = max(1, min(99, (int)$cart_row['product_quantity']));

    $stmt = $conn->prepare(
        "SELECT product_id, product_name, product_image FROM products WHERE product_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        continue; // product gone; skip
    }
    $line_items[] = [
        'product_id'       => (int)$product['product_id'],
        'product_name'     => $product['product_name'],
        'product_image'    => $product['product_image'],
        'product_quantity' => $qty,
    ];
    $totalQty += $qty;
}

if (empty($line_items) || $totalQty <= 0) {
    header('Location: ../cart.php');
    exit;
}

// ---- Apply tiered pricing -------------------------------------------------
$unitPrice = tier_unit_price($totalQty);
if ($unitPrice <= 0) {
    error_log('place_order: tier price resolved to 0 for qty ' . $totalQty);
    header('Location: ../checkout.php?message=Could not price your order, please try again.');
    exit;
}
$subtotal = round($unitPrice * $totalQty, 2);
foreach ($line_items as &$li) {
    $li['product_price'] = $unitPrice; // overrides DB price per business rule
}
unset($li);

// ---- Validate coupon (server-side re-check) -------------------------------
$user_id        = (int)$_SESSION['user_id'];
$applied_coupon = coupon_get_applied($conn);
$discount       = 0.0;
$coupon_id      = null;
$coupon_code    = '';

if ($applied_coupon) {
    $check = coupon_validate($conn, $applied_coupon, $subtotal, $user_id);
    if ($check['ok']) {
        $discount    = $check['discount'];
        $coupon_id   = (int)$applied_coupon['coupon_id'];
        $coupon_code = (string)$applied_coupon['code'];
    } else {
        coupon_clear_session();
    }
}

$shipping   = SHIPPING_FEE;
$order_cost = round(max(0.0, $subtotal - $discount) + $shipping, 2);
$order_status = 'not paid';
$order_date   = date('Y-m-d H:i:s');

// ---- Insert order + items + coupon redemption in one transaction ----------
$conn->begin_transaction();

$stmt = $conn->prepare(
    "INSERT INTO orders
        (order_cost, subtotal, shipping, discount_amount, coupon_id,
         tier_unit_price, total_quantity, order_status,
         user_id, user_name, user_email, user_phone, user_city, user_address, order_date)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
// 15 placeholders, types in column order:
//   order_cost(d) subtotal(d) shipping(d) discount_amount(d) coupon_id(i)
//   tier_unit_price(d) total_quantity(i) order_status(s)
//   user_id(i) user_name(s) user_email(s) user_phone(s) user_city(s) user_address(s) order_date(s)
$stmt->bind_param(
    'ddddidisissssss',
    $order_cost, $subtotal, $shipping, $discount, $coupon_id,
    $unitPrice, $totalQty, $order_status,
    $user_id, $name, $email, $phone, $city, $address, $order_date
);

if (!$stmt->execute()) {
    $conn->rollback();
    error_log('Order insert failed');
    header('Location: ../checkout.php?message=Could not place order, please try again.');
    exit;
}
$order_id = $stmt->insert_id;
$stmt->close();

// Line items
$itemStmt = $conn->prepare(
    "INSERT INTO order_items
        (order_id, product_id, product_name, product_image, product_price,
         product_quantity, user_id, order_date)
     VALUES (?,?,?,?,?,?,?,?)"
);
foreach ($line_items as $li) {
    $itemStmt->bind_param(
        'iissdiis',
        $order_id,
        $li['product_id'],
        $li['product_name'],
        $li['product_image'],
        $li['product_price'],
        $li['product_quantity'],
        $user_id,
        $order_date
    );
    if (!$itemStmt->execute()) {
        $conn->rollback();
        error_log('Order item insert failed');
        header('Location: ../checkout.php?message=Could not place order, please try again.');
        exit;
    }
}
$itemStmt->close();

// Coupon redemption
if ($coupon_id !== null && $discount > 0) {
    $stmt = $conn->prepare(
        "INSERT INTO coupon_redemptions (coupon_id, user_id, order_id, discount_amount, redeemed_at)
         VALUES (?,?,?,?,?)"
    );
    $stmt->bind_param('iiids', $coupon_id, $user_id, $order_id, $discount, $order_date);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE coupon_id=?");
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $stmt->close();
}

$conn->commit();

// Clear cart + coupon now that order is safely persisted
unset($_SESSION['cart'], $_SESSION['total'], $_SESSION['quantity']);
coupon_clear_session();
$_SESSION['order_id'] = $order_id;

// ---- Send order-placed emails (best effort) -------------------------------
try {
    $orderForEmail = [
        'order_id'        => $order_id,
        'user_name'       => $name,
        'user_email'      => $email,
        'user_phone'      => $phone,
        'user_city'       => $city,
        'user_address'    => $address,
        'subtotal'        => $subtotal,
        'shipping'        => $shipping,
        'discount_amount' => $discount,
        'order_cost'      => $order_cost,
        'tier_unit_price' => $unitPrice,
        'total_quantity'  => $totalQty,
        'coupon_code'     => $coupon_code,
    ];
    send_order_placed_emails($orderForEmail, $line_items);
} catch (Throwable $t) {
    error_log('Order email send failed: ' . $t->getMessage());
}

header('Location: ../payment.php?order_status=order_placed');
exit;
