<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_check();

$order_id = (int)($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    header('Location: index.php?order_failed=1');
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM orders WHERE order_id=?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header('Location: index.php?order_deleted=1');
} catch (Throwable $t) {
    $conn->rollback();
    error_log('Order delete failed: ' . $t->getMessage());
    header('Location: index.php?order_failed=1');
}
exit;
