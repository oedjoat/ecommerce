<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/image_upload.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create_product'])) {
    header('Location: add_product.php');
    exit;
}

csrf_check();

// ---- Validate text inputs -------------------------------------------------
$valid_categories = ['clothing', 'jackets', 'shoes', 'watches'];

$product_name        = trim((string)($_POST['name']        ?? ''));
$product_description = trim((string)($_POST['description'] ?? ''));
$product_price       = (float)($_POST['price']  ?? 0);
$product_offer       = (int)  ($_POST['offer']  ?? 0);
$product_category    = strtolower(trim((string)($_POST['category'] ?? '')));
$product_color       = trim((string)($_POST['color'] ?? ''));

if ($product_name === '' || mb_strlen($product_name) > 100) {
    header('Location: add_product.php?error=' . urlencode('Invalid product name.'));
    exit;
}
if ($product_description === '' || mb_strlen($product_description) > 2000) {
    header('Location: add_product.php?error=' . urlencode('Invalid description.'));
    exit;
}
if ($product_price < 0 || $product_price > 100000) {
    header('Location: add_product.php?error=' . urlencode('Invalid price.'));
    exit;
}
if ($product_offer < 0 || $product_offer > 100) {
    header('Location: add_product.php?error=' . urlencode('Offer must be 0-100.'));
    exit;
}
if (!in_array($product_category, $valid_categories, true)) {
    header('Location: add_product.php?error=' . urlencode('Invalid category.'));
    exit;
}
if ($product_color === '' || mb_strlen($product_color) > 40) {
    header('Location: add_product.php?error=' . urlencode('Invalid color.'));
    exit;
}

// ---- Validate and store uploads -------------------------------------------
$uploadDir = realpath(__DIR__ . '/../assets/imgs') ?: (__DIR__ . '/../assets/imgs');
$savedNames = [];

try {
    foreach (['image1', 'image2', 'image3', 'image4'] as $key) {
        if (!isset($_FILES[$key])) {
            throw new RuntimeException("Missing image: $key");
        }
        $savedNames[$key] = save_uploaded_image($_FILES[$key], $uploadDir);
    }
} catch (RuntimeException $e) {
    // Roll back any successful uploads
    foreach ($savedNames as $name) {
        delete_stored_image($uploadDir, $name);
    }
    header('Location: add_product.php?error=' . urlencode($e->getMessage()));
    exit;
}

// ---- Insert into DB -------------------------------------------------------
$stmt = $conn->prepare(
    "INSERT INTO products
     (product_name, product_description, product_price, product_special_offer,
      product_image, product_image2, product_image3, product_image4,
      product_category, product_color)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
);
$stmt->bind_param(
    'ssdissssss',
    $product_name, $product_description, $product_price, $product_offer,
    $savedNames['image1'], $savedNames['image2'], $savedNames['image3'], $savedNames['image4'],
    $product_category, $product_color
);

if ($stmt->execute()) {
    $stmt->close();
    header('Location: products.php?product_created=1');
} else {
    $stmt->close();
    foreach ($savedNames as $name) {
        delete_stored_image($uploadDir, $name);
    }
    header('Location: products.php?product_failed=1');
}
exit;
