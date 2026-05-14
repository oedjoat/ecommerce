<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

$valid_statuses = ['not paid', 'paid', 'shipped', 'delivered'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_order'])) {
    csrf_check();

    $order_status = (string)($_POST['order_status'] ?? '');
    $order_id     = (int)($_POST['order_id'] ?? 0);

    if ($order_id <= 0 || !in_array($order_status, $valid_statuses, true)) {
        header('Location: index.php?order_failed=1');
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET order_status=? WHERE order_id=?");
    $stmt->bind_param('si', $order_status, $order_id);
    $ok = $stmt->execute();
    $stmt->close();

    header('Location: index.php?' . ($ok ? 'order_updated' : 'order_failed') . '=1');
    exit;
}

if (!isset($_GET['order_id'])) {
    header('Location: index.php');
    exit;
}

$order_id = (int)$_GET['order_id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id=? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
    header('Location: index.php');
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

            <h2>Edit Order</h2>
            <div class="table-responsive">
                <div class="mx-auto container">
                    <form id="edit-order-form" method="POST" action="edit_order.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= e((string)$r['order_id']) ?>"/>

                        <div class="form-group my-3">
                            <label>Order ID</label>
                            <p class="my-4"><?= e((string)$r['order_id']) ?></p>
                        </div>
                        <div class="form-group mt-3">
                            <label>Order Cost</label>
                            <p class="my-4">$<?= e(number_format((float)$r['order_cost'], 2)) ?></p>
                        </div>
                        <div class="form-group my-3">
                            <label>Order Status</label>
                            <select class="form-select" required name="order_status">
                                <?php foreach ($valid_statuses as $s): ?>
                                    <option value="<?= e($s) ?>"
                                        <?= $r['order_status'] === $s ? 'selected' : '' ?>>
                                        <?= e(ucfirst($s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group my-3">
                            <label>Order Date</label>
                            <p class="my-4"><?= e((string)$r['order_date']) ?></p>
                        </div>
                        <div class="form-group mt-3">
                            <input type="submit" class="btn btn-primary" name="edit_order" value="Save" />
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
