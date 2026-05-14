<?php
// server/oxapay_create.php - kicks off an OxaPay payment for an order.
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/oxapay.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../account.php');
    exit;
}

csrf_check();

$user_id  = (int)$_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    header('Location: ../account.php');
    exit;
}

// Confirm order belongs to user and is unpaid; pull cost + customer email
$stmt = $conn->prepare(
    "SELECT order_id, order_cost, order_status, user_email
     FROM orders WHERE order_id=? AND user_id=? LIMIT 1"
);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: ../account.php');
    exit;
}
if ($order['order_status'] !== 'not paid') {
    header('Location: ../account.php?payment_message=This order has already been paid.');
    exit;
}

global $OXAPAY_CURRENCY, $APP_BASE_URL;

if ($APP_BASE_URL === '') {
    error_log('OxaPay create failed: APP_BASE_URL not configured.');
    header('Location: ../payment.php?order_id=' . $order_id . '&oxa_error=' . urlencode('Payment is not configured.'));
    exit;
}

$payload = [
    'amount'       => number_format((float)$order['order_cost'], 2, '.', ''),
    'currency'     => $OXAPAY_CURRENCY,
    'lifetime'     => 30, // minutes
    'order_id'     => (string)$order_id,
    'email'        => (string)$order['user_email'],
    'description'  => 'Kimmi order #' . $order_id,
    'callback_url' => $APP_BASE_URL . '/server/oxapay_callback.php',
    'return_url'   => $APP_BASE_URL . '/server/oxapay_return.php?order_id=' . $order_id,
];

try {
    $invoice = oxapay_create_invoice($payload);
} catch (Throwable $t) {
    error_log('OxaPay invoice create failed for order ' . $order_id . ': ' . $t->getMessage());
    header('Location: ../payment.php?order_id=' . $order_id . '&oxa_error=' . urlencode('Could not start crypto payment. Please try again.'));
    exit;
}

// Mark the chosen payment method on the order so the UI/email can reflect it
$method = 'oxapay';
$stmt = $conn->prepare("UPDATE orders SET payment_method=? WHERE order_id=? AND user_id=?");
$stmt->bind_param('sii', $method, $order_id, $user_id);
$stmt->execute();
$stmt->close();

header('Location: ' . $invoice['pay_link']);
exit;
