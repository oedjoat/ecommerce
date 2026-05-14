<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

$valid_categories = ['clothing', 'jackets', 'shoes', 'watches'];
$product = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_button'])) {
    csrf_check();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $title      = trim((string)($_POST['title']       ?? ''));
    $desc       = trim((string)($_POST['description'] ?? ''));
    $price      = (float)($_POST['price'] ?? 0);
    $offer      = (int)  ($_POST['offer'] ?? 0);
    $color      = trim((string)($_POST['color']    ?? ''));
    $category   = strtolower(trim((string)($_POST['category'] ?? '')));

    if ($product_id <= 0
        || $title === '' || mb_strlen($title) > 100
        || $desc === ''  || mb_strlen($desc)  > 2000
        || $price < 0    || $price > 100000
        || $offer < 0    || $offer > 100
        || $color === '' || mb_strlen($color) > 40
        || !in_array($category, $valid_categories, true)) {
        header('Location: products.php?edit_failure_message=1');
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE products SET product_name=?, product_description=?, product_price=?,
                product_special_offer=?, product_color=?, product_category=?
         WHERE product_id=?"
    );
    $stmt->bind_param('ssdissi', $title, $desc, $price, $offer, $color, $category, $product_id);
    $ok = $stmt->execute();
    $stmt->close();

    header('Location: products.php?' . ($ok ? 'edit_success_message' : 'edit_failure_message') . '=1');
    exit;
}

// GET - load existing product
if (!isset($_GET['product_id'])) {
    header('Location: products.php');
    exit;
}

$product_id = (int)$_GET['product_id'];
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id=? LIMIT 1");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: products.php');
    exit;
}
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

            <h2>Edit Product</h2>
            <div class="table-responsive">
                <div class="mx-auto container">
                    <form id="edit-form" method="POST" action="edit_product.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id"
                               value="<?= e((string)(int)$product['product_id']) ?>" />

                        <div class="form-group mt-2">
                            <label>Title</label>
                            <input type="text" class="form-control" name="title" maxlength="100" required
                                   value="<?= e((string)$product['product_name']) ?>" />
                        </div>
                        <div class="form-group mt-2">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="4"
                                      maxlength="2000" required><?= e((string)$product['product_description']) ?></textarea>
                        </div>
                        <div class="form-group mt-2">
                            <label>Price</label>
                            <input type="number" step="0.01" min="0" max="100000"
                                   class="form-control" name="price" required
                                   value="<?= e((string)$product['product_price']) ?>" />
                        </div>
                        <div class="form-group mt-2">
                            <label>Category</label>
                            <select class="form-select" required name="category">
                                <?php foreach ($valid_categories as $cat): ?>
                                    <option value="<?= e($cat) ?>"
                                        <?= strtolower((string)$product['product_category']) === $cat ? 'selected' : '' ?>>
                                        <?= e(ucfirst($cat)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mt-2">
                            <label>Color</label>
                            <input type="text" class="form-control" name="color" maxlength="40" required
                                   value="<?= e((string)$product['product_color']) ?>" />
                        </div>
                        <div class="form-group mt-2">
                            <label>Special Offer (%)</label>
                            <input type="number" min="0" max="100" class="form-control" name="offer" required
                                   value="<?= e((string)$product['product_special_offer']) ?>" />
                        </div>

                        <div class="form-group mt-3">
                            <input type="submit" class="btn btn-primary" name="edit_button" value="Save"/>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
