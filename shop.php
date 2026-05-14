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

    <style>
        .product img { width: 100%; height: auto; box-sizing: border-box; object-fit: cover; }
        .pagination a { color: coral; }
        .pagination li:hover a { color: #fff; background-color: coral; }
    </style>

    <!-- SEARCH -->
    <section id="search" class="my-5 py-5 ms-2">
        <div class="container mt-5 py-5">
            <h3>Search Products</h3>
            <hr>
        </div>

        <form action="shop.php" method="POST">
            <?= csrf_field() ?>
            <div class="row mx-auto container">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <p>Category</p>
                    <?php foreach ($valid_categories as $idx => $cat): ?>
                        <div class="form-check">
                            <input class="form-check-input" value="<?= e($cat) ?>" type="radio"
                                   name="category" id="category_<?= $idx ?>"
                                   <?= $category === $cat ? 'checked' : '' ?>>
                            <label class="form-check-label" for="category_<?= $idx ?>">
                                <?= e(ucfirst($cat)) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row mx-auto container mt-5">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <p>Max price: $<span id="priceValue"><?= e((string)($price ?: 100)) ?></span></p>
                    <input type="range" class="form-range w-50" name="price"
                           value="<?= e((string)($price ?: 100)) ?>" min="1" max="1000"
                           id="customRange2"
                           oninput="document.getElementById('priceValue').textContent=this.value">
                    <div class="w-50">
                        <span style="float: left;">1</span>
                        <span style="float: right;">1000</span>
                    </div>
                </div>
            </div>

            <div class="form-group my-3 mx-3">
                <input type="submit" name="search" value="Search" class="btn btn-primary">
                <?php if ($has_filter): ?>
                    <a href="shop.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- PRODUCTS -->
    <section id="shop" class="my-5 py-5">
        <div class="container mt-5 py-5">
            <h3>Our Products</h3>
            <hr>
            <p>Here you can check out our products</p>
        </div>

        <div class="row mx-auto container">
            <?php while ($row = $products->fetch_assoc()): ?>
                <div class="product text-center col-lg-3 col-md-4 col-sm-12">
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <img class="img-fluid mb-3" src="assets/imgs/<?= e((string)$row['product_image']) ?>"
                             alt="<?= e((string)$row['product_name']) ?>" />
                    </a>
                    <div class="star">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        <i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <h5 class="p-name"><?= e((string)$row['product_name']) ?></h5>
                    <h4 class="p-price">$<?= e(number_format((float)$row['product_price'], 2)) ?></h4>
                    <a class="btn buy-btn"
                       href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        Buy Now
                    </a>
                </div>
            <?php endwhile; ?>

            <nav aria-label="pagination" class="mx-auto">
                <ul class="pagination mt-5 mx-auto">
                    <li class="page-item <?= $page_no <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link"
                           href="<?= $page_no <= 1 ? '#' : e(page_link($page_no - 1, $category, $price)) ?>">
                            Previous
                        </a>
                    </li>
                    <?php for ($p = 1; $p <= $total_no_of_pages; $p++): ?>
                        <li class="page-item <?= $p === $page_no ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(page_link($p, $category, $price)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page_no >= $total_no_of_pages ? 'disabled' : '' ?>">
                        <a class="page-link"
                           href="<?= $page_no >= $total_no_of_pages ? '#' : e(page_link($page_no + 1, $category, $price)) ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
