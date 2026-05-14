<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../server/oxapay.php';
require_admin();

$lookup_error = '';
$lookup_data  = null;
$history_error = '';
$history_data  = null;

// ----- Action: look up a single payment by track_id ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    csrf_check();
    $trackId = trim((string)($_POST['track_id'] ?? ''));
    if ($trackId === '') {
        $lookup_error = 'Please enter a track id.';
    } else {
        try {
            $lookup_data = oxapay_payment_info($trackId);
        } catch (Throwable $t) {
            $lookup_error = $t->getMessage();
        }
    }
}

// ----- Action: fetch history from OxaPay ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['history'])) {
    csrf_check();
    $size = max(1, min(50, (int)($_POST['size'] ?? 20)));
    $page = max(1, (int)($_POST['page'] ?? 1));
    try {
        $history_data = oxapay_payment_history(['size' => $size, 'page' => $page]);
    } catch (Throwable $t) {
        $history_error = $t->getMessage();
    }
}

// ----- Local OxaPay payments (what we've already received) ----------------
$local = $conn->prepare(
    "SELECT p.payment_id, p.order_id, p.transaction_id, p.amount, p.currency, p.payment_date,
            o.user_name, o.user_email, o.order_status
       FROM payments p
       JOIN orders o ON o.order_id = p.order_id
      WHERE p.provider = 'oxapay'
      ORDER BY p.payment_date DESC
      LIMIT 25"
);
$local->execute();
$local_rows = $local->get_result();
$local->close();

/** Pretty-print a value for display in the table. */
function _fmt($v): string {
    if (is_array($v)) return e(json_encode($v, JSON_UNESCAPED_SLASHES));
    if (is_bool($v))  return $v ? 'true' : 'false';
    if ($v === null)  return '—';
    return e((string)$v);
}
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">OxaPay Payments</h1>
            </div>

            <!-- ===== Local payments (already received via webhook) ===== -->
            <h3>Recent crypto payments (local)</h3>
            <p class="text-muted" style="font-size:0.9rem;">
                Pulled from the <code>payments</code> table where <code>provider='oxapay'</code>.
                Click a track id to look it up live on OxaPay.
            </p>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Track ID</th>
                            <th>Order status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($r = $local_rows->fetch_assoc()): ?>
                        <tr>
                            <td><?= e((string)$r['payment_date']) ?></td>
                            <td>#<?= (int)$r['order_id'] ?></td>
                            <td>
                                <?= e((string)$r['user_name']) ?><br>
                                <small class="text-muted"><?= e((string)$r['user_email']) ?></small>
                            </td>
                            <td>
                                <?= e((string)$r['currency']) ?>
                                <?= e(number_format((float)$r['amount'], 2)) ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="track_id"
                                           value="<?= e((string)$r['transaction_id']) ?>">
                                    <button type="submit" name="lookup" class="btn btn-sm btn-link"
                                            style="padding:0;">
                                        <?= e((string)$r['transaction_id']) ?>
                                    </button>
                                </form>
                            </td>
                            <td><?= e((string)$r['order_status']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($local_rows->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center">No OxaPay payments received yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ===== Live lookup by track_id ===== -->
            <h3 class="mt-5">Look up a payment by track_id</h3>
            <form method="POST" style="max-width: 500px;">
                <?= csrf_field() ?>
                <div class="form-group" style="display:flex; gap:8px;">
                    <input type="text" name="track_id" maxlength="64" class="form-control"
                           placeholder="e.g. 168931368" required
                           value="<?= e((string)($_POST['track_id'] ?? '')) ?>">
                    <button type="submit" name="lookup" class="btn btn-primary">Lookup</button>
                </div>
            </form>

            <?php if ($lookup_error): ?>
                <p style="color:red;"><?= e($lookup_error) ?></p>
            <?php endif; ?>

            <?php if ($lookup_data): ?>
                <h4 class="mt-3">Result</h4>
                <table class="table table-sm" style="max-width: 800px;">
                    <tbody>
                    <?php foreach ($lookup_data as $k => $v): ?>
                        <?php if ($k === 'data') continue; ?>
                        <tr>
                            <th style="width: 30%;"><?= e((string)$k) ?></th>
                            <td><?= _fmt($v) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- ===== Live history from OxaPay ===== -->
            <h3 class="mt-5">Payment history (live from OxaPay)</h3>
            <form method="POST" style="display:flex; gap:8px; max-width: 500px;">
                <?= csrf_field() ?>
                <input type="number" name="page" min="1" value="<?= e((string)($_POST['page'] ?? 1)) ?>"
                       class="form-control" placeholder="Page">
                <input type="number" name="size" min="1" max="50"
                       value="<?= e((string)($_POST['size'] ?? 20)) ?>"
                       class="form-control" placeholder="Per page">
                <button type="submit" name="history" class="btn btn-primary">Load</button>
            </form>

            <?php if ($history_error): ?>
                <p style="color:red;" class="mt-2"><?= e($history_error) ?></p>
            <?php endif; ?>

            <?php if ($history_data && !empty($history_data['list']) && is_array($history_data['list'])): ?>
                <?php $meta = $history_data['meta'] ?? null; ?>
                <?php if ($meta): ?>
                    <p class="mt-2 text-muted" style="font-size:0.9rem;">
                        Page <?= e((string)($meta['page'] ?? 1)) ?>
                        of <?= e((string)($meta['last_page'] ?? 1)) ?>
                        (<?= e((string)($meta['total'] ?? 0)) ?> records)
                    </p>
                <?php endif; ?>
                <div class="table-responsive mt-2">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Track ID</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history_data['list'] as $row): ?>
                            <tr>
                                <td><?= e(date('Y-m-d H:i', (int)($row['date'] ?? 0))) ?></td>
                                <td><?= e((string)($row['track_id'] ?? '')) ?></td>
                                <td><?= e((string)($row['order_id'] ?? '')) ?></td>
                                <td><?= e((string)($row['status']   ?? '')) ?></td>
                                <td>
                                    <?= e((string)($row['currency'] ?? '')) ?>
                                    <?= e((string)($row['amount']   ?? '')) ?>
                                </td>
                                <td><?= e((string)($row['email']    ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($history_data): ?>
                <p class="mt-2 text-muted">No payments returned for that page.</p>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>