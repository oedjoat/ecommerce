<?php
require_once __DIR__ . '/server/connection.php';

// ---- Inputs (filters can come via GET so pagination preserves them) -------
$category = isset($_GET['category']) ? (string)$_GET['category'] : '';
$price    = isset($_GET['price'])    ? (int)$_GET['price']       : 0;
$page_no  = isset($_GET['page_no'])  ? max(1, (int)$_GET['page_no']) : 1;

// If form was just submitted via POST, redirect to GET with filters in URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $cat = (string)($_POST['category'] ?? '');
    $pr  = (int)($_POST['price'] ?? 0);
    $qs = http_build_query(['category' => $cat, 'price' => $pr, 'page_no' => 1]);
    header('Location: shop.php?' . $qs);
    exit;
}

$valid_categories = ['clothing', 'jackets', 'shoes', 'watches'];
$has_filter = in_array($category, $valid_categories, true) && $price > 0;

$total_records_per_page = 8;
$offset = ($page_no - 1) * $total_records_per_page;

// ---- Counts and product fetch with LIMIT/OFFSET as bound integers ---------
if ($has_filter) {
    // Count
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM products WHERE LOWER(product_category)=? AND product_price<=?"
    );
    $stmt->bind_param('si', $category, $price);
    $stmt->execute();
    $stmt->bind_result($total_records);
    $stmt->fetch();
    $stmt->close();

    // Page rows
    $stmt = $conn->prepare(
        "SELECT product_id, product_name, product_image, product_price
         FROM products WHERE LOWER(product_category)=? AND product_price<=?
         ORDER BY product_id DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('siii', $category, $price, $total_records_per_page, $offset);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM products");
    $stmt->execute();
    $stmt->bind_result($total_records);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT product_id, product_name, product_image, product_price
         FROM products ORDER BY product_id DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('ii', $total_records_per_page, $offset);
}
$stmt->execute();
$products = $stmt->get_result();

$total_no_of_pages = max(1, (int)ceil($total_records / $total_records_per_page));

/** Build a query string preserving current filters but switching the page. */
function page_link(int $page, string $category, int $price): string {
    $params = ['page_no' => $page];
    if ($category !== '') $params['category'] = $category;
    if ($price > 0)       $params['price']    = $price;
    return 'shop.php?' . http_build_query($params);
}

include __DIR__ . '/layouts/header.php';
?>

    <div style="padding-top: 90px;">
    <div class="container" style="padding-top: 3rem; padding-bottom: 5rem;">
        <div id="shop-layout">

            <!-- SIDEBAR FILTER -->
            <aside id="search">
                <h3>Filter</h3>
                <form action="shop.php" method="POST">
                    <?= csrf_field() ?>

                    <p class="mb-2" style="font-size:0.82rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#191919;">Category</p>
                    <?php foreach ($valid_categories as $idx => $cat): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" value="<?= e($cat) ?>" type="radio"
                                   name="category" id="category_<?= $idx ?>"
                                   <?= $category === $cat ? 'checked' : '' ?>>
                            <label class="form-check-label" for="category_<?= $idx ?>">
                                <?= e(ucfirst($cat)) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-4">
                        <p class="mb-2" style="font-size:0.82rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#191919;">
                            Max Price: $<span id="priceValue"><?= e((string)($price ?: 1000)) ?></span>
                        </p>
                        <input type="range" class="form-range" name="price"
                               value="<?= e((string)($price ?: 1000)) ?>" min="1" max="1000"
                               id="customRange2"
                               oninput="document.getElementById('priceValue').textContent=this.value">
                        <div class="d-flex justify-content-between" style="font-size:0.78rem;color:#8d8d8d;">
                            <span>$1</span><span>$1000</span>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 flex-wrap">
                        <button type="submit" name="search" style="padding:10px 20px;font-size:0.75rem;">Search</button>
                        <?php if ($has_filter): ?>
                            <a href="shop.php" class="btn btn-outline" style="padding:10px 20px;font-size:0.75rem;border:1.5px solid #191919;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </aside>

            <!-- PRODUCTS GRID -->
            <section id="shop">
                <div class="shop-section-header">
                    <h3>Our Products</h3>
                    <hr>
                    <p style="margin-top:0.6rem;color:#8d8d8d;font-size:0.9rem;">
                        <?= $total_records ?> product<?= $total_records !== 1 ? 's' : '' ?> found
                    </p>
                </div>

                <div class="row g-4">
                    <?php while ($row = $products->fetch_assoc()): ?>
                        <div class="product col-lg-4 col-md-6 col-sm-6 col-12">
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
                            <h4 class="p-price">$<?= e(number_format((float)$row['product_price'], 2)) ?></h4>
                            <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                                <span class="buy-btn">Buy Now</span>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>

                <nav aria-label="pagination" class="mt-5">
                    <ul class="pagination">
                        <li class="page-item <?= $page_no <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page_no <= 1 ? '#' : e(page_link($page_no - 1, $category, $price)) ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_no_of_pages; $p++): ?>
                            <li class="page-item <?= $p === $page_no ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(page_link($p, $category, $price)) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page_no >= $total_no_of_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page_no >= $total_no_of_pages ? '#' : e(page_link($page_no + 1, $category, $price)) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </section>

        </div><!-- /#shop-layout -->
    </div>
    </div>

<?php include __DIR__ . '/layouts/footer.php'; ?>
