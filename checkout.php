<?php
require_once __DIR__ . '/server/connection.php';

if (empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

$subtotal = (float)($_SESSION['total'] ?? 0);
$user_id  = (int)($_SESSION['user_id'] ?? 0);

// Re-validate any applied coupon at this stage
$applied_coupon = coupon_get_applied($conn);
$discount = 0.0;
if ($applied_coupon) {
    $check = coupon_validate($conn, $applied_coupon, $subtotal, $user_id);
    if ($check['ok']) {
        $discount = $check['discount'];
    } else {
        coupon_clear_session();
        $applied_coupon = null;
    }
}

$shipping  = SHIPPING_FEE;
$grand     = max(0.0, $subtotal - $discount) + $shipping;
$totalQty  = cart_total_quantity();
$unitPrice = tier_unit_price($totalQty);

// Prefill from session
$prefill_name  = (string)($_SESSION['user_name']  ?? '');
$prefill_email = (string)($_SESSION['user_email'] ?? '');

include __DIR__ . '/layouts/header.php';
?>

    <!-- CHECKOUT -->
    <section class="my-5 py-5">
        <div class="container text-center mt-3 pt-5">
            <h2 class="form-weight-bold">Check Out</h2>
            <hr class="mx-auto">
        </div>

        <div class="mx-auto container">
            <form id="checkout-form" action="server/place_order.php" method="POST">
                <?= csrf_field() ?>
                <?php if (!empty($_GET['message'])): ?>
                    <p style="color: red;" class="text-center">
                        <?= e((string)$_GET['message']) ?>
                        <?php if (empty($_SESSION['logged_in'])): ?>
                            <a class="btn btn-primary" href="login.php">Login</a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <div class="form-group checkout-small-element">
                    <label>Name</label>
                    <input type="text" class="form-control" id="checkout-name"
                           name="name" maxlength="100" placeholder="Name" required
                           value="<?= e($prefill_name) ?>" />
                </div>
                <div class="form-group checkout-small-element">
                    <label>Email</label>
                    <input type="email" class="form-control" id="checkout-email"
                           name="email" maxlength="254" placeholder="Email" required
                           value="<?= e($prefill_email) ?>" />
                </div>
                <div class="form-group checkout-small-element">
                    <label>Phone</label>
                    <input type="tel" class="form-control" id="checkout-phone"
                           name="phone" maxlength="32" pattern="[0-9 +()\-]+"
                           placeholder="Phone" required />
                </div>
                <div class="form-group checkout-small-element">
                    <label>City</label>
                    <input type="text" class="form-control" id="checkout-city"
                           name="city" maxlength="100" placeholder="City" required />
                </div>
                <div class="form-group checkout-large-element">
                    <label>Address</label>
                    <input type="text" class="form-control" id="checkout-address"
                           name="address" maxlength="255" placeholder="Address" required />
                </div>

                <div class="form-group checkout-btn-container">
                    <p>Unit price (tier for <?= $totalQty ?> items): $<?= e(number_format($unitPrice, 2)) ?></p>
                    <p>Subtotal: $<?= e(number_format($subtotal, 2)) ?></p>
                    <?php if ($applied_coupon): ?>
                        <p>Coupon (<?= e((string)$applied_coupon['code']) ?>):
                           -$<?= e(number_format($discount, 2)) ?></p>
                    <?php endif; ?>
                    <p>Shipping: $<?= e(number_format($shipping, 2)) ?></p>
                    <p><strong>Total: $<?= e(number_format($grand, 2)) ?></strong></p>
                    <input type="submit" class="btn" id="checkout-btn"
                           name="place_order" value="Place Order" />
                </div>
            </form>
        </div>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
