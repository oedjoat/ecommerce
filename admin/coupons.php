<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

$page_no = max(1, (int)($_GET['page_no'] ?? 1));
$per_page = 20;
$offset = ($page_no - 1) * $per_page;

$stmt = $conn->prepare("SELECT COUNT(*) FROM coupons");
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare(
    "SELECT coupon_id, code, discount_type, discount_value, min_order,
            max_uses, times_used, per_user_limit, expires_at, active, created_at
     FROM coupons ORDER BY coupon_id DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param('ii', $per_page, $offset);
$stmt->execute();
$coupons = $stmt->get_result();

$total_pages = max(1, (int)ceil($total_records / $per_page));

$flashes = [
    'created'   => ['green', 'Coupon created.'],
    'updated'   => ['green', 'Coupon updated.'],
    'deleted'   => ['green', 'Coupon deleted.'],
    'failed'    => ['red',   'Operation failed.'],
    'duplicate' => ['red',   'A coupon with that code already exists.'],
    'invalid'   => ['red',   'Invalid coupon details.'],
];
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Coupons</h1>
            </div>

            <?php foreach ($flashes as $key => [$color, $text]): ?>
                <?php if (isset($_GET[$key])): ?>
                    <p class="text-center" style="color: <?= e($color) ?>;"><?= e($text) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Create form -->
            <details style="margin-bottom: 1rem;">
                <summary style="cursor:pointer; padding:8px; background:#f1f1f1;">
                    <strong>+ Create new coupon</strong>
                </summary>
                <form method="POST" action="create_coupon.php" class="mt-3" style="max-width: 600px;">
                    <?= csrf_field() ?>
                    <div class="form-group mt-2">
                        <label>Code (uppercase, 3-50 chars)</label>
                        <input type="text" class="form-control" name="code" required
                               maxlength="50" pattern="[A-Za-z0-9_-]{3,50}" />
                    </div>
                    <div class="form-group mt-2">
                        <label>Discount type</label>
                        <select class="form-select" name="discount_type" required>
                            <option value="percent">Percent (e.g. 10 = 10%)</option>
                            <option value="fixed">Fixed amount (e.g. 25 = $25 off)</option>
                        </select>
                    </div>
                    <div class="form-group mt-2">
                        <label>Discount value</label>
                        <input type="number" step="0.01" min="0.01" max="100000"
                               class="form-control" name="discount_value" required />
                    </div>
                    <div class="form-group mt-2">
                        <label>Min order subtotal ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control"
                               name="min_order" value="0" required />
                    </div>
                    <div class="form-group mt-2">
                        <label>Max total uses (blank = unlimited)</label>
                        <input type="number" min="1" class="form-control" name="max_uses" />
                    </div>
                    <div class="form-group mt-2">
                        <label>Per-user limit (blank = unlimited)</label>
                        <input type="number" min="1" class="form-control" name="per_user_limit" />
                    </div>
                    <div class="form-group mt-2">
                        <label>Expires at (blank = never)</label>
                        <input type="datetime-local" class="form-control" name="expires_at" />
                    </div>
                    <div class="form-group mt-2 form-check">
                        <input type="checkbox" name="active" value="1" checked id="active-cb">
                        <label for="active-cb">Active</label>
                    </div>
                    <div class="form-group mt-3">
                        <input type="submit" class="btn btn-primary" name="create" value="Create" />
                    </div>
                </form>
            </details>

            <!-- Listing -->
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Min order</th>
                            <th>Uses</th>
                            <th>Per user</th>
                            <th>Expires</th>
                            <th>Active</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($c = $coupons->fetch_assoc()): ?>
                        <tr>
                            <td><?= e((string)$c['coupon_id']) ?></td>
                            <td><strong><?= e((string)$c['code']) ?></strong></td>
                            <td>
                                <?php if ($c['discount_type'] === 'percent'): ?>
                                    <?= e((string)$c['discount_value']) ?>%
                                <?php else: ?>
                                    $<?= e(number_format((float)$c['discount_value'], 2)) ?>
                                <?php endif; ?>
                            </td>
                            <td>$<?= e(number_format((float)$c['min_order'], 2)) ?></td>
                            <td>
                                <?= e((string)$c['times_used']) ?>
                                / <?= $c['max_uses'] === null ? '&infin;' : e((string)$c['max_uses']) ?>
                            </td>
                            <td>
                                <?= $c['per_user_limit'] === null ? '&infin;' : e((string)$c['per_user_limit']) ?>
                            </td>
                            <td><?= $c['expires_at'] ? e((string)$c['expires_at']) : '—' ?></td>
                            <td>
                                <?php if ((int)$c['active'] === 1): ?>
                                    <span style="color:green;">Yes</span>
                                <?php else: ?>
                                    <span style="color:#888;">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn btn-primary"
                                   href="edit_coupon.php?coupon_id=<?= (int)$c['coupon_id'] ?>">Edit</a>
                            </td>
                            <td>
                                <form method="POST" action="delete_coupon.php"
                                      onsubmit="return confirm('Delete this coupon?');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($total_records === 0): ?>
                        <tr><td colspan="10" class="text-center">No coupons yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mx-auto">
                    <ul class="pagination mt-5 mx-auto">
                        <li class="page-item <?= $page_no <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page_no <= 1 ? '#' : '?page_no=' . ($page_no - 1) ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p === $page_no ? 'active' : '' ?>">
                                <a class="page-link" href="?page_no=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page_no >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page_no >= $total_pages ? '#' : '?page_no=' . ($page_no + 1) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
