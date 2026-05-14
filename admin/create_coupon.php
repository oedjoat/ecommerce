<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create'])) {
    header('Location: coupons.php');
    exit;
}

csrf_check();

$code           = strtoupper(trim((string)($_POST['code'] ?? '')));
$discount_type  = (string)($_POST['discount_type'] ?? '');
$discount_value = (float)($_POST['discount_value'] ?? 0);
$min_order      = (float)($_POST['min_order'] ?? 0);
$active         = isset($_POST['active']) ? 1 : 0;

$max_uses_raw       = trim((string)($_POST['max_uses']       ?? ''));
$per_user_limit_raw = trim((string)($_POST['per_user_limit'] ?? ''));
$expires_at_raw     = trim((string)($_POST['expires_at']     ?? ''));

$max_uses       = $max_uses_raw       === '' ? null : (int)$max_uses_raw;
$per_user_limit = $per_user_limit_raw === '' ? null : (int)$per_user_limit_raw;
$expires_at     = $expires_at_raw     === '' ? null : date('Y-m-d H:i:s', strtotime($expires_at_raw));

if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)
    || !in_array($discount_type, ['percent', 'fixed'], true)
    || $discount_value <= 0
    || ($discount_type === 'percent' && $discount_value > 100)
    || $discount_value > 100000
    || $min_order < 0
    || ($max_uses !== null && $max_uses < 1)
    || ($per_user_limit !== null && $per_user_limit < 1)) {
    header('Location: coupons.php?invalid=1');
    exit;
}

// Duplicate code check
$stmt = $conn->prepare("SELECT COUNT(*) FROM coupons WHERE code=?");
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->bind_result($exists);
$stmt->fetch();
$stmt->close();
if ((int)$exists > 0) {
    header('Location: coupons.php?duplicate=1');
    exit;
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO coupons
            (code, discount_type, discount_value, min_order, max_uses,
             per_user_limit, expires_at, active)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        'ssddiisi',
        $code, $discount_type, $discount_value, $min_order,
        $max_uses, $per_user_limit, $expires_at, $active
    );
    $stmt->execute();
    $stmt->close();
    header('Location: coupons.php?created=1');
} catch (Throwable $t) {
    error_log('Coupon create failed: ' . $t->getMessage());
    header('Location: coupons.php?failed=1');
}
exit;
