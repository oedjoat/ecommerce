<?php
require_once __DIR__ . '/server/connection.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// ---- Logout ---------------------------------------------------------------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---- Change password ------------------------------------------------------
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_check();

    $password         = (string)($_POST['password'] ?? '');
    $confirmPassword  = (string)($_POST['confirmPassword'] ?? '');
    $user_id          = (int)$_SESSION['user_id'];

    if ($password !== $confirmPassword) {
        $error = "Passwords don't match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET user_password=? WHERE user_id=?");
        $stmt->bind_param('si', $hash, $user_id);
        $message = $stmt->execute()
            ? "Password has been updated successfully."
            : "Could not update password.";
        $stmt->close();
    }
}

// ---- Fetch orders --------------------------------------------------------
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT order_id, order_cost, order_status, order_date
     FROM orders WHERE user_id=? ORDER BY order_date DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
$order_ids = [];
while ($r = $res->fetch_assoc()) {
    $orders[] = $r;
    $order_ids[] = (int)$r['order_id'];
}
$stmt->close();

// ---- Bulk-fetch recipients for those orders ------------------------------
$rfields = recipient_field_names();
$recipients_by_order = [];
if (!empty($order_ids)) {
    // Safe to interpolate ids since they came straight from the DB as ints.
    $idList = implode(',', array_map('intval', $order_ids));
    $colSql = '`' . implode('`,`', $rfields) . '`';
    $sql = "SELECT order_id, seq, $colSql FROM order_recipients
            WHERE order_id IN ($idList) ORDER BY order_id, seq";
    if ($rres = $conn->query($sql)) {
        while ($row = $rres->fetch_assoc()) {
            $recipients_by_order[(int)$row['order_id']][] = $row;
        }
        $rres->free();
    }
}

include __DIR__ . '/layouts/header.php';
?>

    <!-- Account -->
    <section class="my-5 py-5">
        <div class="row container mx-auto">
            <?php if (!empty($_GET['payment_message'])): ?>
                <p class="mt-5 text-center" style="color: green;">
                    <?= e((string)$_GET['payment_message']) ?>
                </p>
            <?php endif; ?>

            <div class="text-center mt-3 pt-5 col-lg-6 col-md-12 col-sm-12">
                <?php if (isset($_GET['register_success'])): ?>
                    <p style="color:green;" class="text-center">You registered successfully.</p>
                <?php endif; ?>
                <?php if (isset($_GET['login_success'])): ?>
                    <p style="color:green;" class="text-center">Logged in successfully.</p>
                <?php endif; ?>

                <h3 class="font-weight-bold">Account Info</h3>
                <hr class="mx-auto">
                <div class="account-info">
                    <p>Name: <span><?= e($_SESSION['user_name'] ?? '') ?></span></p>
                    <p>Email: <span><?= e($_SESSION['user_email'] ?? '') ?></span></p>
                    <p><a href="#orders" id="orders-btn">Your Orders</a></p>
                    <p><a href="account.php?logout=1" id="logout-btn">Logout</a></p>
                </div>
            </div>

            <div class="col-lg-6 col-md-12 col-sm-12">
                <form id="account-form" method="POST" action="account.php" autocomplete="off">
                    <?= csrf_field() ?>
                    <?php if ($error): ?>
                        <p style="color:red;" class="text-center"><?= e($error) ?></p>
                    <?php endif; ?>
                    <?php if ($message): ?>
                        <p style="color:green;" class="text-center"><?= e($message) ?></p>
                    <?php endif; ?>

                    <h3>Change Password</h3>
                    <hr class="mx-auto">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" class="form-control" id="account-password" name="password"
                               minlength="8" placeholder="Password (min 8 chars)" required />
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" class="form-control" id="account-confirm-password"
                               name="confirmPassword" minlength="8" placeholder="Confirm Password" required />
                    </div>
                    <div class="form-group">
                        <input type="submit" value="Change Password" name="change_password"
                               class="btn" id="change-pass-btn" />
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- ORDERS -->
    <section id="orders" class="orders container my-5 py-3">
        <div class="container mt-2">
            <h2 class="font-weight-bold text-center">Your Orders</h2>
            <hr class="mx-auto">
        </div>

        <table class="mt-5 pt-5">
            <tr>
                <th>Order ID</th>
                <th>Order Cost</th>
                <th>Order Status</th>
                <th>Order Date</th>
                <th>Order Details</th>
                <th>Recipients</th>
            </tr>

            <?php foreach ($orders as $row): ?>
                <?php
                    $oid = (int)$row['order_id'];
                    $rec = $recipients_by_order[$oid] ?? [];
                ?>
                <tr>
                    <td><span><?= e((string)$row['order_id']) ?></span></td>
                    <td><span>$<?= e(number_format((float)$row['order_cost'], 2)) ?></span></td>
                    <td><span><?= e((string)$row['order_status']) ?></span></td>
                    <td><span><?= e((string)$row['order_date']) ?></span></td>
                    <td>
                        <form method="POST" action="order_details.php">
                            <?= csrf_field() ?>
                            <input type="hidden" value="<?= e((string)$row['order_id']) ?>" name="order_id" />
                            <input class="btn order-details-btn" name="order_details_btn"
                                   type="submit" value="Details" />
                        </form>
                    </td>
                    <td><span><?= count($rec) ?></span></td>
                </tr>
                <?php if (!empty($rec)): ?>
                <tr>
                    <td colspan="6" style="padding:0 20px 12px 20px;">
                        <details style="border:1px solid #eee; padding:8px 12px; border-radius:4px;">
                            <summary style="cursor:pointer; font-weight:600;">
                                Recipients for order #<?= $oid ?> (<?= count($rec) ?>)
                            </summary>
                            <div style="margin-top:8px;">
                                <?php foreach ($rec as $r): ?>
                                    <div style="border:1px solid #f1f1f1; padding:6px 10px; margin-bottom:6px; border-radius:3px;">
                                        <div style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">
                                            Recipient #<?= e((string)$r['seq']) ?>
                                        </div>
                                        <table style="border-collapse:collapse;font-size:0.85rem;width:100%;">
                                            <?php foreach ($rfields as $f): ?>
                                                <tr>
                                                    <td style="padding:2px 8px;color:#666;width:150px;border-bottom:1px solid #f6f6f6;">
                                                        <?= e(recipient_field_label($f)) ?>
                                                    </td>
                                                    <td style="padding:2px 8px;border-bottom:1px solid #f6f6f6;">
                                                        <?= e((string)($r[$f] ?? '')) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="text-center">You have no orders yet.</td></tr>
            <?php endif; ?>
        </table>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
