<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/image_upload.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_images'])) {
    header('Location: products.php');
    exit;
}

csrf_check();

$product_id = (int)($_POST['product_id'] ?? 0);
if ($product_id <= 0) {
    header('Location: products.php?images_failed=1');
    exit;
}

$uploadDir = realpath(__DIR__ . '/../assets/imgs') ?: (__DIR__ . '/../assets/imgs');

// Capture existing image filenames so we can delete them after a successful swap
$stmt = $conn->prepare(
    "SELECT product_image, product_image2, product_image3, product_image4
     FROM products WHERE product_id=?"
);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$old) {
    header('Location: products.php?images_failed=1');
    exit;
}

// Try to save all four uploads; rollback on any failure
$savedNames = [];
try {
    foreach (['image1','image2','image3','image4'] as $key) {
        if (!isset($_FILES[$key])) {
            throw new RuntimeException("Missing file: $key");
        }
        $savedNames[$key] = save_uploaded_image($_FILES[$key], $uploadDir);
    }
} catch (RuntimeException $e) {
    foreach ($savedNames as $name) {
        delete_stored_image($uploadDir, $name);
    }
    error_log('Image upload failed: ' . $e->getMessage());
    header('Location: products.php?images_failed=1');
    exit;
}

$stmt = $conn->prepare(
    "UPDATE products SET product_image=?, product_image2=?, product_image3=?, product_image4=?
     WHERE product_id=?"
);
$stmt->bind_param(
    'ssssi',
    $savedNames['image1'], $savedNames['image2'], $savedNames['image3'], $savedNames['image4'],
    $product_id
);

if ($stmt->execute()) {
    $stmt->close();
    // Successful update - clean up old files
    foreach (['product_image','product_image2','product_image3','product_image4'] as $col) {
        delete_stored_image($uploadDir, $old[$col] ?? null);
    }
    header('Location: products.php?images_updated=1');
} else {
    $stmt->close();
    foreach ($savedNames as $name) {
        delete_stored_image($uploadDir, $name);
    }
    header('Location: products.php?images_failed=1');
}
exit;
