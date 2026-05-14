<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/mailer.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_GET['transaction_id']) || empty($_GET['order_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id        = (int)$_SESSION['user_id'];
$order_id       = (int)$_GET['order_id'];
$transaction_id = trim((string)$_GET['transaction_id']);
$payment_date   = date('Y-m-d H:i:s');
$paid_status    = 'paid';
$provider       = 'paypal';

if ($order_id <= 0 || $transaction_id === '' || mb_strlen($transaction_id) > 64) {
    header('Location: ../index.php');
    exit;
}

// Confirm order belongs to this user and is unpaid; pull amount + customer info
$stmt = $conn->prepare(
    "SELECT order_id, order_cost, user_name, user_email
     FROM orders WHERE order_id=? AND user_id=? AND order_status='not paid' LIMIT 1"
);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: ../account.php');
    exit;
}

$amount   = (float)$order['order_cost'];
$currency = 'USD';

$conn->begin_transaction();
try {
    // Mark order paid + record provider
    $stmt = $conn->prepare(
        "UPDATE orders SET order_status=?, payment_method=?
         WHERE order_id=? AND user_id=? AND order_status='not paid'"
    );
    $stmt->bind_param('ssii', $paid_status, $provider, $order_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $conn->rollback();
        header('Location: ../account.php');
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO payments
            (order_id, user_id, provider, transaction_id, amount, currency, payment_date)
         VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        'iissdss',
        $order_id, $user_id, $provider, $transaction_id, $amount, $currency, $payment_date
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $t) {
    $conn->rollback();
    error_log('Payment completion failed: ' . $t->getMessage());
    header('Location: ../account.php?payment_message=Payment recorded but status update failed; please contact support.');
    exit;
}

// Send "payment received" emails to customer + admin (best effort)
try {
    send_payment_confirmed_emails($order, $provider, $transaction_id);
} catch (Throwable $t) {
    error_log('Payment confirmation email failed: ' . $t->getMessage());
}

unset($_SESSION['order_id']);
header('Location: ../account.php?payment_message=Paid successfully, thanks for your patronage');
exit;
