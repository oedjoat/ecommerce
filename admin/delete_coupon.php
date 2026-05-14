<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: coupons.php');
    exit;
}

csrf_check();

$coupon_id = (int)($_POST['coupon_id'] ?? 0);
if ($coupon_id <= 0) {
    header('Location: coupons.php?failed=1');
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM coupons WHERE coupon_id=?");
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $stmt->close();
    header('Location: coupons.php?deleted=1');
} catch (Throwable $t) {
    error_log('Coupon delete failed: ' . $t->getMessage());
    header('Location: coupons.php?failed=1');
}
exit;
