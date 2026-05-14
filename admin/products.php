<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

$page_no = max(1, (int)($_GET['page_no'] ?? 1));
$total_records_per_page = 10;
$offset = ($page_no - 1) * $total_records_per_page;

$stmt = $conn->prepare("SELECT COUNT(*) FROM products");
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare(
    "SELECT product_id, product_image, product_name, product_price,
            product_special_offer, product_category, product_color
     FROM products ORDER BY product_id DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param('ii', $total_records_per_page, $offset);
$stmt->execute();
$products = $stmt->get_result();

$total_no_of_pages = max(1, (int)ceil($total_records / $total_records_per_page));

$flashes = [
    'edit_success_message' => ['green', 'Product has been updated successfully.'],
    'edit_failure_message' => ['red',   'Error occurred, try again.'],
    'deleted_successfully' => ['green', 'Product has been deleted.'],
    'deleted_failure'      => ['red',   'Could not delete product.'],
    'product_created'      => ['green', 'Product has been created successfully.'],
    'product_failed'       => ['red',   'Error occurred while trying to create product.'],
    'images_updated'       => ['green', 'Images have been updated successfully.'],
    'images_failed'        => ['red',   'Error occurred while trying to update images.'],
];
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

            <h2>Products</h2>

            <?php foreach ($flashes as $key => [$color, $text]): ?>
                <?php if (isset($_GET[$key])): ?>
                    <p class="text-center" style="color: <?= e($color) ?>;"><?= e($text) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Product Id</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Offer</th>
                            <th>Category</th>
                            <th>Color</th>
                            <th>Edit Images</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td><?= e((string)$product['product_id']) ?></td>
                            <td>
                                <img src="../assets/imgs/<?= e((string)$product['product_image']) ?>"
                                     style="width: 70px; height: 70px;"
                                     alt="<?= e((string)$product['product_name']) ?>"/>
                            </td>
                            <td><?= e((string)$product['product_name']) ?></td>
                            <td>$<?= e(number_format((float)$product['product_price'], 2)) ?></td>
                            <td><?= e((string)$product['product_special_offer']) ?>%</td>
                            <td><?= e((string)$product['product_category']) ?></td>
                            <td><?= e((string)$product['product_color']) ?></td>
                            <td>
                                <a class="btn btn-warning"
                                   href="edit_images.php?product_id=<?= e((string)(int)$product['product_id']) ?>">
                                    Edit Images
                                </a>
                            </td>
                            <td>
                                <a class="btn btn-primary"
                                   href="edit_product.php?product_id=<?= e((string)(int)$product['product_id']) ?>">
                                    Edit
                                </a>
                            </td>
                            <td>
                                <form method="POST" action="delete_product.php"
                                      onsubmit="return confirm('Delete this product?');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id"
                                           value="<?= e((string)(int)$product['product_id']) ?>">
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <nav aria-label="Page navigation" class="mx-auto">
                    <ul class="pagination mt-5 mx-auto">
                        <li class="page-item <?= $page_no <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link"
                               href="<?= $page_no <= 1 ? '#' : '?page_no=' . ($page_no - 1) ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_no_of_pages; $p++): ?>
                            <li class="page-item <?= $p === $page_no ? 'active' : '' ?>">
                                <a class="page-link" href="?page_no=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page_no >= $total_no_of_pages ? 'disabled' : '' ?>">
                            <a class="page-link"
                               href="<?= $page_no >= $total_no_of_pages ? '#' : '?page_no=' . ($page_no + 1) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </main>
    </div>
</div>
</body>
</html>
