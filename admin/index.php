<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

$page_no = max(1, (int)($_GET['page_no'] ?? 1));
$total_records_per_page = 10;
$offset = ($page_no - 1) * $total_records_per_page;

$stmt = $conn->prepare("SELECT COUNT(*) FROM orders");
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare(
    "SELECT order_id, order_status, user_id, order_date, user_phone, user_address
     FROM orders ORDER BY order_date DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param('ii', $total_records_per_page, $offset);
$stmt->execute();
$orders = $stmt->get_result();

$total_no_of_pages = max(1, (int)ceil($total_records / $total_records_per_page));
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

            <h2>Orders</h2>

            <?php if (!empty($_GET['order_updated'])): ?>
                <p class="text-center" style="color:green;">Order has been updated successfully.</p>
            <?php elseif (!empty($_GET['order_failed'])): ?>
                <p class="text-center" style="color:red;">Failed to update order.</p>
            <?php elseif (!empty($_GET['order_deleted'])): ?>
                <p class="text-center" style="color:green;">Order has been deleted.</p>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Order Id</th>
                            <th>Order Status</th>
                            <th>User Id</th>
                            <th>Order Date</th>
                            <th>User Phone</th>
                            <th>User Address</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?= e((string)$order['order_id']) ?></td>
                            <td><?= e((string)$order['order_status']) ?></td>
                            <td><?= e((string)$order['user_id']) ?></td>
                            <td><?= e((string)$order['order_date']) ?></td>
                            <td><?= e((string)$order['user_phone']) ?></td>
                            <td><?= e((string)$order['user_address']) ?></td>
                            <td>
                                <a class="btn btn-primary"
                                   href="edit_order.php?order_id=<?= e((string)(int)$order['order_id']) ?>">Edit</a>
                            </td>
                            <td>
                                <form method="POST" action="delete_order.php"
                                      onsubmit="return confirm('Delete this order?');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id"
                                           value="<?= e((string)(int)$order['order_id']) ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
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
