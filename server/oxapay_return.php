<?php
// server/oxapay_return.php - destination after OxaPay payment.
// The webhook is the source of truth for marking orders paid; this handler
// just gives the user a clean landing page based on the current order status.

require_once __DIR__ . '/connection.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit;
}

$order_id = (int)($_GET['order_id'] ?? 0);
$user_id  = (int)$_SESSION['user_id'];

if ($order_id <= 0) {
    header('Location: ../account.php');
    exit;
}

$stmt = $conn->prepare(
    "SELECT order_status FROM orders WHERE order_id=? AND user_id=? LIMIT 1"
);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: ../account.php');
    exit;
}

if ($row['order_status'] === 'not paid') {
    // Payment may still be confirming on-chain; OxaPay's callback will mark it
    // paid once funds settle.
    header('Location: ../account.php?payment_message=' . urlencode(
        'Thanks - your crypto payment is being confirmed. Your order will update as soon as the network confirms it.'
    ));
} else {
    header('Location: ../account.php?payment_message=Paid successfully, thanks for your patronage');
}
exit;
