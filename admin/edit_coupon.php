<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_check();

    $coupon_id      = (int)($_POST['coupon_id'] ?? 0);
    $discount_type  = (string)($_POST['discount_type'] ?? '');
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_order      = (float)($_POST['min_order'] ?? 0);
    $active         = isset($_POST['active']) ? 1 : 0;

    $max_uses_raw       = trim((string)($_POST['max_uses']       ?? ''));
    $per_user_limit_raw = trim((string)($_POST['per_user_limit'] ?? ''));
    $expires_at_raw     = trim((string)($_POST['expires_at']     ?? ''));

    $max_uses       = $max_uses_raw       === '' ? null : (int)$max_uses_raw;
    $per_user_limit = $per_user_limit_raw === '' ? null : (int)$per_user_limit_raw;
    $expires_at     = $expires_at_raw     === '' ? null : date('Y-m-d H:i:s', strtotime($expires_at_raw));

    if ($coupon_id <= 0
        || !in_array($discount_type, ['percent', 'fixed'], true)
        || $discount_value <= 0
        || ($discount_type === 'percent' && $discount_value > 100)
        || $discount_value > 100000
        || $min_order < 0
        || ($max_uses !== null && $max_uses < 1)
        || ($per_user_limit !== null && $per_user_limit < 1)) {
        header('Location: coupons.php?invalid=1');
        exit;
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE coupons SET discount_type=?, discount_value=?, min_order=?,
                    max_uses=?, per_user_limit=?, expires_at=?, active=?
             WHERE coupon_id=?"
        );
        $stmt->bind_param(
            'sddiisii',
            $discount_type, $discount_value, $min_order,
            $max_uses, $per_user_limit, $expires_at, $active, $coupon_id
        );
        $stmt->execute();
        $stmt->close();
        header('Location: coupons.php?updated=1');
    } catch (Throwable $t) {
        error_log('Coupon update failed: ' . $t->getMessage());
        header('Location: coupons.php?failed=1');
    }
    exit;
}

// GET - load
$coupon_id = (int)($_GET['coupon_id'] ?? 0);
if ($coupon_id <= 0) {
    header('Location: coupons.php');
    exit;
}

$stmt = $conn->prepare(
    "SELECT * FROM coupons WHERE coupon_id=? LIMIT 1"
);
$stmt->bind_param('i', $coupon_id);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coupon) {
    header('Location: coupons.php');
    exit;
}

$expires_value = '';
if (!empty($coupon['expires_at'])) {
    $ts = strtotime($coupon['expires_at']);
    if ($ts) $expires_value = date('Y-m-d\TH:i', $ts);
}
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Coupon</h1>
            </div>

            <form method="POST" action="edit_coupon.php" style="max-width: 600px;">
                <?= csrf_field() ?>
                <input type="hidden" name="coupon_id" value="<?= (int)$coupon['coupon_id'] ?>">

                <div class="form-group mt-2">
                    <label>Code</label>
                    <p><strong><?= e((string)$coupon['code']) ?></strong>
                       <small class="text-muted">(code is immutable)</small></p>
                </div>

                <div class="form-group mt-2">
                    <label>Discount type</label>
                    <select class="form-select" name="discount_type" required>
                        <option value="percent" <?= $coupon['discount_type'] === 'percent' ? 'selected' : '' ?>>Percent</option>
                        <option value="fixed"   <?= $coupon['discount_type'] === 'fixed'   ? 'selected' : '' ?>>Fixed</option>
                    </select>
                </div>
                <div class="form-group mt-2">
                    <label>Discount value</label>
                    <input type="number" step="0.01" min="0.01" max="100000" class="form-control"
                           name="discount_value" required
                           value="<?= e((string)$coupon['discount_value']) ?>" />
                </div>
                <div class="form-group mt-2">
                    <label>Min order subtotal ($)</label>
                    <input type="number" step="0.01" min="0" class="form-control"
                           name="min_order" required
                           value="<?= e((string)$coupon['min_order']) ?>" />
                </div>
                <div class="form-group mt-2">
                    <label>Max total uses (blank = unlimited)</label>
                    <input type="number" min="1" class="form-control" name="max_uses"
                           value="<?= $coupon['max_uses'] === null ? '' : e((string)$coupon['max_uses']) ?>" />
                </div>
                <div class="form-group mt-2">
                    <label>Per-user limit (blank = unlimited)</label>
                    <input type="number" min="1" class="form-control" name="per_user_limit"
                           value="<?= $coupon['per_user_limit'] === null ? '' : e((string)$coupon['per_user_limit']) ?>" />
                </div>
                <div class="form-group mt-2">
                    <label>Expires at</label>
                    <input type="datetime-local" class="form-control" name="expires_at"
                           value="<?= e($expires_value) ?>" />
                </div>
                <div class="form-group mt-2">
                    <label>Times already used: <?= (int)$coupon['times_used'] ?></label>
                </div>
                <div class="form-group mt-2 form-check">
                    <input type="checkbox" name="active" value="1"
                           <?= (int)$coupon['active'] === 1 ? 'checked' : '' ?> id="active-cb">
                    <label for="active-cb">Active</label>
                </div>

                <div class="form-group mt-3">
                    <input type="submit" class="btn btn-primary" name="save" value="Save" />
                    <a href="coupons.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </main>
    </div>
</div>
</body>
</html>
