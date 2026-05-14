<?php
require_once __DIR__ . '/server/connection.php';

// ---- Helpers --------------------------------------------------------------

/** Load product from DB; cart never trusts client-supplied price/name. */
function fetch_product(mysqli $conn, int $product_id): ?array {
    $stmt = $conn->prepare(
        "SELECT product_id, product_name, product_image
         FROM products WHERE product_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Recompute totals using the tiered unit price (based on TOTAL cart quantity).
 * Sets $_SESSION['total'] (subtotal) and $_SESSION['quantity'].
 */
function recalc_cart(): void {
    $total_quantity = cart_total_quantity();
    $unit_price     = tier_unit_price($total_quantity);
    $subtotal       = $unit_price * $total_quantity;
    $_SESSION['total']    = round($subtotal, 2);
    $_SESSION['quantity'] = $total_quantity;
}

// ---- Handle POSTs ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // --- Add a product ------------------------------------------------------
    if (isset($_POST['add_to_cart'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty        = max(1, min(99, (int)($_POST['product_quantity'] ?? 1)));
        $product    = $product_id > 0 ? fetch_product($conn, $product_id) : null;

        if ($product) {
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['product_quantity'] =
                    min(99, $_SESSION['cart'][$product_id]['product_quantity'] + $qty);
            } else {
                $_SESSION['cart'][$product_id] = [
                    'product_id'       => (int)$product['product_id'],
                    'product_name'     => $product['product_name'],
                    'product_image'    => $product['product_image'],
                    'product_quantity' => $qty,
                ];
            }
        }
    }

    // --- Remove ------------------------------------------------------------
    elseif (isset($_POST['remove_product'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$product_id]);
    }

    // --- Edit qty ----------------------------------------------------------
    elseif (isset($_POST['edit_quantity'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty        = max(1, min(99, (int)($_POST['product_quantity'] ?? 1)));
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['product_quantity'] = $qty;
        }
    }

    // --- Apply coupon ------------------------------------------------------
    elseif (isset($_POST['apply_coupon'])) {
        recalc_cart();
        $code     = (string)($_POST['coupon_code'] ?? '');
        $subtotal = (float)($_SESSION['total'] ?? 0);
        $user_id  = (int)($_SESSION['user_id'] ?? 0);
        $coupon   = coupon_find_by_code($conn, $code);
        $check    = coupon_validate($conn, $coupon, $subtotal, $user_id);

        if ($check['ok']) {
            coupon_apply_session($check['coupon']['code']);
            $_SESSION['coupon_message'] = ['ok', 'Coupon ' . $check['coupon']['code'] . ' applied.'];
        } else {
            coupon_clear_session();
            $_SESSION['coupon_message'] = ['err', $check['error'] ?? 'Invalid coupon.'];
        }
    }

    // --- Remove coupon -----------------------------------------------------
    elseif (isset($_POST['remove_coupon'])) {
        coupon_clear_session();
        $_SESSION['coupon_message'] = ['ok', 'Coupon removed.'];
    }

    recalc_cart();

    // PRG so refresh doesn't re-submit
    header('Location: cart.php');
    exit;
}

// Make sure totals are present on first GET, and validate any session coupon
recalc_cart();
$subtotal = (float)($_SESSION['total'] ?? 0);
$user_id  = (int)($_SESSION['user_id'] ?? 0);

$applied_coupon = coupon_get_applied($conn);
$discount       = 0.0;
if ($applied_coupon) {
    $check = coupon_validate($conn, $applied_coupon, $subtotal, $user_id);
    if ($check['ok']) {
        $discount = $check['discount'];
    } else {
        // Coupon no longer valid (e.g. dropped below min_order); drop it silently.
        coupon_clear_session();
        $applied_coupon = null;
        $_SESSION['coupon_message'] = ['err', $check['error'] ?? 'Coupon no longer applies.'];
    }
}

$shipping = $subtotal > 0 ? SHIPPING_FEE : 0.00;
$grand    = max(0.0, $subtotal - $discount) + $shipping;
$unitPrice = tier_unit_price(cart_total_quantity());

$coupon_message = $_SESSION['coupon_message'] ?? null;
unset($_SESSION['coupon_message']);

include __DIR__ . '/layouts/header.php';
?>

<!-- CART -->
<section class="cart container my-5 py-5">
    <div class="container mt-5">
        <h2 class="font-weight-bold">Your Cart</h2>
        <hr>
        <p class="text-muted" style="font-size:0.9rem;">
            Pricing is volume-based: 1 item $120, 2-4 $105, 5-10 $95, 11-20 $85, 21-30 $70, 31-50 $65, 51+ $60.
            <?php if ($unitPrice > 0): ?>
                <strong>Current unit price: $<?= e(number_format($unitPrice, 2)) ?></strong>
                (<?= (int)$_SESSION['quantity'] ?> items in cart).
            <?php endif; ?>
        </p>
    </div>

    <table class="mt-5 pt-5">
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Subtotal</th>
        </tr>

        <?php if (!empty($_SESSION['cart'])): ?>
            <?php foreach ($_SESSION['cart'] as $value): ?>
                <tr>
                    <td>
                        <div class="product-info">
                            <img src="assets/imgs/<?= e((string)$value['product_image']) ?>"
                                 alt="<?= e((string)$value['product_name']) ?>">
                            <div>
                                <p><?= e((string)$value['product_name']) ?></p>
                                <small>$<?= e(number_format($unitPrice, 2)) ?> /unit</small>
                                <br>
                                <form method="POST" action="cart.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id"
                                           value="<?= e((string)$value['product_id']) ?>" />
                                    <input type="submit" name="remove_product"
                                           class="remove-btn" value="remove" />
                                </form>
                            </div>
                        </div>
                    </td>

                    <td>
                        <form method="POST" action="cart.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id"
                                   value="<?= e((string)$value['product_id']) ?>" />
                            <input type="number" name="product_quantity"
                                   min="1" max="99"
                                   value="<?= e((string)$value['product_quantity']) ?>">
                            <input type="submit" class="edit-btn" value="edit" name="edit_quantity" />
                        </form>
                    </td>

                    <td>
                        $<span class="product-price">
                            <?= e(number_format((float)$value['product_quantity'] * $unitPrice, 2)) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="3" class="text-center">Your cart is empty.</td></tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($_SESSION['cart'])): ?>
        <!-- COUPON -->
        <div class="cart-total" style="margin-top: 30px;">
            <table>
                <?php if ($coupon_message): ?>
                    <tr>
                        <td colspan="2" style="color: <?= $coupon_message[0] === 'ok' ? 'green' : 'red' ?>;">
                            <?= e($coupon_message[1]) ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if ($applied_coupon): ?>
                    <tr>
                        <td>
                            Coupon: <strong><?= e((string)$applied_coupon['code']) ?></strong>
                            <form method="POST" action="cart.php" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="submit" name="remove_coupon" value="remove"
                                       class="remove-btn" style="padding:0 0 0 8px;" />
                            </form>
                        </td>
                        <td style="text-align:right;">-$<?= e(number_format($discount, 2)) ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="2">
                            <form method="POST" action="cart.php" style="display:flex; gap:8px;">
                                <?= csrf_field() ?>
                                <input type="text" name="coupon_code" placeholder="Coupon code"
                                       maxlength="50" style="flex:1;padding:6px;" />
                                <input type="submit" name="apply_coupon" value="Apply"
                                       class="edit-btn" style="padding:6px 12px;" />
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

    <div class="cart-total">
        <table>
            <tr><td>Subtotal</td>
                <td>$<?= e(number_format($subtotal, 2)) ?></td></tr>
            <?php if ($discount > 0): ?>
                <tr><td>Discount</td>
                    <td>-$<?= e(number_format($discount, 2)) ?></td></tr>
            <?php endif; ?>
            <tr><td>Shipping</td>
                <td>$<?= e(number_format($shipping, 2)) ?></td></tr>
            <tr><td><strong>Total</strong></td>
                <td><strong>$<?= e(number_format($grand, 2)) ?></strong></td></tr>
        </table>
    </div>

    <?php if (!empty($_SESSION['cart'])): ?>
    <div class="checkout-container">
        <form method="POST" action="checkout.php">
            <?= csrf_field() ?>
            <input type="submit" class="btn checkout-btn" value="Checkout" name="checkout" />
        </form>
    </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
