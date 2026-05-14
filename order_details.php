<?php
require_once __DIR__ . '/server/connection.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['order_details_btn'], $_POST['order_id'])) {
    header('Location: account.php');
    exit;
}

csrf_check();

$order_id = (int)$_POST['order_id'];
$user_id  = (int)$_SESSION['user_id'];

// IMPORTANT: ensure the order belongs to this logged-in user
$stmt = $conn->prepare(
    "SELECT order_id, order_status, order_cost FROM orders WHERE order_id=? AND user_id=? LIMIT 1"
);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: account.php');
    exit;
}

$order_status = $order['order_status'];

// Fetch line items
$stmt = $conn->prepare(
    "SELECT product_name, product_image, product_price, product_quantity
     FROM order_items WHERE order_id=?"
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order_details = $stmt->get_result();

// Compute total from line items
$order_total_price = 0.0;
$rows = [];
while ($r = $order_details->fetch_assoc()) {
    $order_total_price += (float)$r['product_price'] * (int)$r['product_quantity'];
    $rows[] = $r;
}

include __DIR__ . '/layouts/header.php';
?>

    <!-- ORDER Details -->
    <section id="orders" class="orders container my-5 py-3">
        <div class="container mt-5">
            <h2 class="font-weight-bold text-center">Order Details</h2>
            <hr class="mx-auto">
        </div>

        <table class="mt-5 pt-5 mx-auto">
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Quantity</th>
            </tr>

            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div class="product-info">
                            <img src="assets/imgs/<?= e((string)$row['product_image']) ?>"
                                 alt="<?= e((string)$row['product_name']) ?>" />
                            <div>
                                <p class="mt-3"><?= e((string)$row['product_name']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td><span>$<?= e(number_format((float)$row['product_price'], 2)) ?></span></td>
                    <td><span><?= e((string)$row['product_quantity']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($order_status === 'not paid'): ?>
            <form style="float: right;" method="POST" action="payment.php">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id"
                       value="<?= e((string)$order_id) ?>" />
                <input type="hidden" name="order_total_price"
                       value="<?= e(number_format($order_total_price, 2, '.', '')) ?>" />
                <input type="hidden" name="order_status"
                       value="<?= e($order_status) ?>" />
                <input type="submit" name="order_pay_btn" value="Pay Now" class="btn btn-primary"/>
            </form>
        <?php endif; ?>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
