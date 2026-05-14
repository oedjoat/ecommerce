<?php
require_once __DIR__ . '/connection.php';

$stmt = $conn->prepare(
    "SELECT product_id, product_name, product_image, product_price
     FROM products WHERE LOWER(product_category)='shoes' LIMIT 4"
);
$stmt->execute();
$shoes_products = $stmt->get_result();
