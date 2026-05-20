<?php
require_once __DIR__ . '/server/connection.php';

if (!isset($_GET['product_id'])) {
    header('Location: index.php');
    exit;
}

$product_id = (int)$_GET['product_id'];
if ($product_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM products WHERE product_id=? LIMIT 1");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: index.php');
    exit;
}

// Related products: same category, exclude current
$category = (string)$product['product_category'];
$stmt = $conn->prepare(
    "SELECT product_id, product_name, product_image
     FROM products WHERE product_category=? AND product_id<>? LIMIT 4"
);
$stmt->bind_param('si', $category, $product_id);
$stmt->execute();
$related = $stmt->get_result();

$tiers = pricing_tiers();

include __DIR__ . '/layouts/header.php';
?>

    <!-- Single Product -->
    <section class="container single-product my-5 pt-5">
        <div class="row mt-5">
            <div class="col-lg-5 col-md-6 col-sm-12">
                <img class="img-fluid w-100 pb-1" src="assets/imgs/<?= e((string)$product['product_image']) ?>"
                     id="mainImg" alt="<?= e((string)$product['product_name']) ?>">
                <div class="small-img-group">
                    <?php foreach (['product_image','product_image2','product_image3','product_image4'] as $key): ?>
                        <div class="small-img-col">
                            <img src="assets/imgs/<?= e((string)$product[$key]) ?>" width="100%" class="small-img"
                                 alt="" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-6 col-md-12 col-12">
                <h6><?= e((string)$product['product_category']) ?></h6>
                <h3 class="py-4"><?= e((string)$product['product_name']) ?></h3>

                <!-- Volume pricing table replaces a per-product price tag -->
                <div style="margin: 10px 0 20px 0; overflow-x: auto;">
                    <h5 style="margin-bottom:8px;">Volume pricing (per unit)</h5>
                    <table style="border-collapse: collapse; min-width: 280px; width: 100%;">
                        <thead>
                            <tr style="background:#fb774b;color:#fff;">
                                <th style="padding:6px 10px;text-align:left;">Quantity</th>
                                <th style="padding:6px 10px;text-align:right;">Unit price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiers as $tier): ?>
                                <tr>
                                    <td style="padding:6px 10px;border-bottom:1px solid #eee;">
                                        <?php if ($tier['min'] === $tier['max']): ?>
                                            <?= (int)$tier['min'] ?>
                                        <?php elseif ($tier['max'] === PHP_INT_MAX): ?>
                                            <?= (int)$tier['min'] ?>+
                                        <?php else: ?>
                                            <?= (int)$tier['min'] ?>&ndash;<?= (int)$tier['max'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">
                                        $<?= e(number_format((float)$tier['price'], 2)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="font-size:12px;color:#888;margin-top:6px;">
                        Pricing is based on your total cart quantity across all items.
                    </p>
                </div>

                <form method="POST" action="cart.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id"
                           value="<?= e((string)(int)$product['product_id']) ?>" />
                    <input type="number" name="product_quantity" min="1" max="99" value="1" />
                    <button class="buy-btn" type="submit" name="add_to_cart">Add To Cart</button>
                </form>

                <h4 class="mt-5 mb-5">Product details</h4>
                <span><?= nl2br(e((string)$product['product_description'])) ?></span>
            </div>
        </div>
    </section>

    <!-- RELATED PRODUCTS -->
    <section id="related-products" class="my-5 pb-5">
        <div class="container text-center mt-5 py-5">
            <h3>Related Products</h3>
            <hr class="mx-auto">
        </div>
        <div class="container-fluid px-4"><div class="row">
            <?php while ($row = $related->fetch_assoc()): ?>
                <div class="product text-center col-lg-3 col-md-4 col-sm-6 col-12">
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <div class="product-img-wrap">
                            <img src="assets/imgs/<?= e((string)$row['product_image']) ?>"
                                 alt="<?= e((string)$row['product_name']) ?>" />
                        </div>
                    </a>
                    <div class="star">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        <i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <h5 class="p-name"><?= e((string)$row['product_name']) ?></h5>
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <span class="buy-btn">Buy Now</span>
                    </a>
                </div>
            <?php endwhile; ?>
        </div></div>
    </section>

    <script>
        var mainImg = document.getElementById("mainImg");
        var smallImg = document.getElementsByClassName("small-img");
        for (var i = 0; i < smallImg.length; i++) {
            (function (idx) {
                smallImg[idx].onclick = function () { mainImg.src = smallImg[idx].src; };
            })(i);
        }
    </script>

<?php include __DIR__ . '/layouts/footer.php'; ?>
