<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

if (!isset($_GET['product_id'])) {
    header('Location: products.php');
    exit;
}
$product_id = (int)$_GET['product_id'];
if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

// Confirm product exists
$stmt = $conn->prepare("SELECT product_id FROM products WHERE product_id=?");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exists) {
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

            <h2>Update Product Images</h2>
            <div class="table-responsive">
                <div class="mx-auto container">
                    <form id="edit-image-form" enctype="multipart/form-data"
                          method="POST" action="update_images.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id"
                               value="<?= e((string)$product_id) ?>" />

                        <?php foreach (['image1','image2','image3','image4'] as $k): ?>
                            <div class="form-group mt-2">
                                <label><?= e(ucfirst($k)) ?></label>
                                <input type="file" class="form-control" name="<?= e($k) ?>"
                                       accept="image/jpeg,image/png,image/webp" required />
                            </div>
                        <?php endforeach; ?>

                        <div class="form-group mt-3">
                            <input type="submit" class="btn btn-primary" name="update_images" value="Update" />
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
