<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/image_upload.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

csrf_check();

$product_id = (int)($_POST['product_id'] ?? 0);
if ($product_id <= 0) {
    header('Location: products.php?deleted_failure=1');
    exit;
}

// Look up images so we can clean them up
$stmt = $conn->prepare(
    "SELECT product_image, product_image2, product_image3, product_image4
     FROM products WHERE product_id=?"
);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM products WHERE product_id=?");
$stmt->bind_param('i', $product_id);

if ($stmt->execute()) {
    $stmt->close();
    if ($row) {
        $uploadDir = realpath(__DIR__ . '/../assets/imgs') ?: (__DIR__ . '/../assets/imgs');
        foreach (['product_image','product_image2','product_image3','product_image4'] as $col) {
            delete_stored_image($uploadDir, $row[$col] ?? null);
        }
    }
    header('Location: products.php?deleted_successfully=1');
} else {
    $stmt->close();
    header('Location: products.php?deleted_failure=1');
}
exit;
