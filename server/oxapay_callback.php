<?php
// server/oxapay_callback.php - OxaPay webhook receiver (SDK-backed).
// Uses the official SDK to verify the HMAC-SHA512 signature and parse the
// payload, then marks the order paid and records the payment row.
// Must respond HTTP 200 with body "OK" on success to stop OxaPay retries.

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/oxapay.php';
require_once __DIR__ . '/mailer.php';

// Capture the raw body for audit storage BEFORE the SDK consumes php://input.
// In PHP 5.6+ php://input is rewindable, so the SDK can still read it after.
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo 'Empty body';
    exit;
}

try {
    $data = oxapay_webhook_get_data();
} catch (Throwable $t) {
    error_log('OxaPay webhook rejected: ' . $t->getMessage());
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

// Only act on payment-type webhooks (the same endpoint could receive payouts
// if configured; we don't want to mark orders paid on a payout event).
$type = strtolower((string)($data['type'] ?? ''));
if ($type !== 'invoice' && $type !== 'payment') {
    http_response_code(200);
    echo 'OK';
    exit;
}

$order_id = (int)   ($data['order_id'] ?? 0);
$status   = oxapay_normalize_status((string)($data['status'] ?? ''));
$track_id = (string)($data['track_id'] ?? '');
$amount   = (float) ($data['amount']   ?? 0);
$currency = (string)($data['currency'] ?? 'USD');

if ($order_id <= 0) {
    error_log('OxaPay webhook missing order_id; payload=' . substr($raw, 0, 500));
    http_response_code(400);
    echo 'Missing order_id';
    exit;
}

// Only terminal "paid" status triggers a DB write. Intermediate "paying" is
// ignored - OxaPay sends another callback when funds are confirmed.
if ($status !== 'paid') {
    http_response_code(200);
    echo 'OK';
    exit;
}

// ----- Fetch order (and bail out if already paid - idempotency) ------------
$stmt = $conn->prepare(
    "SELECT order_id, user_id, order_cost, order_status, user_name, user_email
     FROM orders WHERE order_id=? LIMIT 1"
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    error_log('OxaPay webhook: order not found, id=' . $order_id);
    http_response_code(200); // ack to stop retries
    echo 'OK';
    exit;
}
if ($order['order_status'] !== 'not paid') {
    http_response_code(200); // already processed
    echo 'OK';
    exit;
}

$user_id      = (int)$order['user_id'];
$payment_date = date('Y-m-d H:i:s');
$provider     = 'oxapay';
$paid_status  = 'paid';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "UPDATE orders SET order_status=? WHERE order_id=? AND order_status='not paid'"
    );
    $stmt->bind_param('si', $paid_status, $order_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    // Another concurrent webhook may have already paid this order; bail.
    if ($affected === 0) {
        $conn->rollback();
        http_response_code(200);
        echo 'OK';
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO payments
            (order_id, user_id, provider, transaction_id, amount, currency, raw_payload, payment_date)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $rawTrimmed = mb_substr($raw, 0, 4000);
    $stmt->bind_param(
        'iissdsss',
        $order_id, $user_id, $provider, $track_id, $amount, $currency, $rawTrimmed, $payment_date
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $t) {
    $conn->rollback();
    error_log('OxaPay webhook DB write failed: ' . $t->getMessage());
    http_response_code(500);
    echo 'DB error';
    exit;
}

// Confirmation emails (best effort; do not fail the webhook on mail issues)
try {
    send_payment_confirmed_emails($order, 'oxapay', $track_id);
} catch (Throwable $t) {
    error_log('OxaPay webhook mail failed: ' . $t->getMessage());
}

http_response_code(200);
echo 'OK';