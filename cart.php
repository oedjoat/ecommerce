<?php
require_once __DIR__ . '/server/connection.php';

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

function recalc_cart(): void {
    $total_quantity = cart_total_quantity();
    $unit_price     = tier_unit_price($total_quantity);
    $subtotal       = $unit_price * $total_quantity;
    $_SESSION['total']    = round($subtotal, 2);
    $_SESSION['quantity'] = $total_quantity;
}

// ---- POST handlers -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

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
    elseif (isset($_POST['remove_product'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$product_id]);
    }
    elseif (isset($_POST['edit_quantity'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty        = max(1, min(99, (int)($_POST['product_quantity'] ?? 1)));
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['product_quantity'] = $qty;
        }
    }
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
    elseif (isset($_POST['remove_coupon'])) {
        coupon_clear_session();
        $_SESSION['coupon_message'] = ['ok', 'Coupon removed.'];
    }
    elseif (isset($_POST['save_recipients'])) {
        $need = cart_total_quantity();
        $submitted = $_POST['recipients'] ?? [];
        $clean = [];
        $errors = [];
        $fields = recipient_field_names();

        for ($i = 0; $i < $need; $i++) {
            $r = is_array($submitted[$i] ?? null) ? $submitted[$i] : [];
            $row = [];
            $rowErr = false;
            foreach ($fields as $f) {
                $v = trim((string)($r[$f] ?? ''));
                $row[$f] = $v;
                if ($v === '') $rowErr = true;
                elseif (mb_strlen($v) > recipient_field_max_length($f)) $rowErr = true;
            }
            $clean[$i] = $row;
            if ($rowErr) $errors[] = '#' . ($i + 1);
        }
        recipients_set($clean);

        if ($errors) {
            $_SESSION['recipients_message'] = ['err',
                'Some recipients incomplete or invalid: ' . implode(', ', $errors)];
        } else {
            $_SESSION['recipients_message'] = ['ok',
                'Recipients saved (' . $need . ' of ' . $need . ' complete).'];
        }
    }

    recalc_cart();
    recipients_resize_to_cart();
    header('Location: cart.php');
    exit;
}

// ---- GET rendering -------------------------------------------------------

recalc_cart();
recipients_resize_to_cart();

$subtotal = (float)($_SESSION['total'] ?? 0);
$user_id  = (int)($_SESSION['user_id'] ?? 0);

$applied_coupon = coupon_get_applied($conn);
$discount = 0.0;
if ($applied_coupon) {
    $check = coupon_validate($conn, $applied_coupon, $subtotal, $user_id);
    if ($check['ok']) {
        $discount = $check['discount'];
    } else {
        coupon_clear_session();
        $applied_coupon = null;
        $_SESSION['coupon_message'] = ['err', $check['error'] ?? 'Coupon no longer applies.'];
    }
}

$shipping  = $subtotal > 0 ? SHIPPING_FEE : 0.00;
$grand     = max(0.0, $subtotal - $discount) + $shipping;
$totalQty  = cart_total_quantity();
$unitPrice = tier_unit_price($totalQty);

$coupon_message     = $_SESSION['coupon_message']     ?? null;
$recipients_message = $_SESSION['recipients_message'] ?? null;
unset($_SESSION['coupon_message'], $_SESSION['recipients_message']);

$recipients      = recipients_get();
$recipients_done = recipients_complete();
$rfields         = recipient_field_names();

include __DIR__ . '/layouts/header.php';
?>

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
        <tr><th>Product</th><th>Quantity</th><th>Subtotal</th></tr>
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
                                    <input type="hidden" name="product_id" value="<?= e((string)$value['product_id']) ?>" />
                                    <input type="submit" name="remove_product" class="remove-btn" value="remove" />
                                </form>
                            </div>
                        </div>
                    </td>
                    <td>
                        <form method="POST" action="cart.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= e((string)$value['product_id']) ?>" />
                            <input type="number" name="product_quantity" min="1" max="99"
                                   value="<?= e((string)$value['product_quantity']) ?>">
                            <input type="submit" class="edit-btn" value="edit" name="edit_quantity" />
                        </form>
                    </td>
                    <td>$<span><?= e(number_format((float)$value['product_quantity'] * $unitPrice, 2)) ?></span></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="3" class="text-center">Your cart is empty.</td></tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($_SESSION['cart'])): ?>
        <div class="cart-total" style="margin-top: 30px;">
            <table>
                <?php if ($coupon_message): ?>
                    <tr><td colspan="2" style="color: <?= $coupon_message[0] === 'ok' ? 'green' : 'red' ?>;">
                        <?= e($coupon_message[1]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php if ($applied_coupon): ?>
                    <tr>
                        <td>
                            Coupon: <strong><?= e((string)$applied_coupon['code']) ?></strong>
                            <form method="POST" action="cart.php" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="submit" name="remove_coupon" value="remove" class="remove-btn" style="padding:0 0 0 8px;" />
                            </form>
                        </td>
                        <td style="text-align:right;">-$<?= e(number_format($discount, 2)) ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="2">
                        <form method="POST" action="cart.php" style="display:flex; gap:8px;">
                            <?= csrf_field() ?>
                            <input type="text" name="coupon_code" placeholder="Coupon code" maxlength="50" style="flex:1;padding:6px;" />
                            <input type="submit" name="apply_coupon" value="Apply" class="edit-btn" style="padding:6px 12px;" />
                        </form>
                    </td></tr>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

    <div class="cart-total">
        <table>
            <tr><td>Subtotal</td><td>$<?= e(number_format($subtotal, 2)) ?></td></tr>
            <?php if ($discount > 0): ?>
                <tr><td>Discount</td><td>-$<?= e(number_format($discount, 2)) ?></td></tr>
            <?php endif; ?>
            <tr><td>Shipping</td><td>$<?= e(number_format($shipping, 2)) ?></td></tr>
            <tr><td><strong>Total</strong></td><td><strong>$<?= e(number_format($grand, 2)) ?></strong></td></tr>
        </table>
    </div>

    <!-- RECIPIENTS - 19 fields per recipient, labels from recipient_field_label() -->
    <?php if ($totalQty > 0): ?>
    <div style="margin-top: 40px;">
        <h3 style="font-weight: 600;">Recipients</h3>
        <hr style="width: 40px; background-color: #fb774b; height: 3px; border: 0; opacity: 1;">
        <p class="text-muted" style="font-size:0.9rem;">
            Fill in details for each of the <strong><?= $totalQty ?></strong>
            item<?= $totalQty === 1 ? '' : 's' ?> in your cart. Changing the cart
            quantity automatically adjusts the form count.
        </p>

        <?php if ($recipients_message): ?>
            <p style="color: <?= $recipients_message[0] === 'ok' ? 'green' : 'red' ?>; font-weight: 600;">
                <?= e($recipients_message[1]) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="cart.php">
            <?= csrf_field() ?>
            <?php for ($i = 0; $i < $totalQty; $i++):
                $r = $recipients[$i] ?? [];
                $isComplete = true;
                foreach ($rfields as $f) {
                    if (trim((string)($r[$f] ?? '')) === '') { $isComplete = false; break; }
                }
            ?>
                <fieldset style="border: 1px solid #ddd; padding: 14px 18px; margin-bottom: 14px; border-radius: 4px;">
                    <legend style="width:auto;padding:0 8px;font-size:0.95rem;">
                        Recipient #<?= $i + 1 ?>
                        <?php if ($isComplete): ?>
                            <span style="color:green;font-weight:600;">&#10004;</span>
                        <?php endif; ?>
                    </legend>
                    <div class="row">
                        <?php foreach ($rfields as $f):
                            $val = (string)($r[$f] ?? '');
                        ?>
                            <div class="form-group col-md-6 col-lg-4 mb-2">
                                <label><?= e(recipient_field_label($f)) ?></label>
                                <input type="text" class="form-control"
                                       maxlength="<?= (int)recipient_field_max_length($f) ?>"
                                       required
                                       name="recipients[<?= $i ?>][<?= e($f) ?>]"
                                       value="<?= e($val) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endfor; ?>

            <button type="submit" name="save_recipients" class="btn btn-primary">Save Recipients</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['cart'])): ?>
        <?php if ($recipients_done): ?>
            <div class="checkout-container" style="margin-top: 24px;">
                <form method="POST" action="checkout.php">
                    <?= csrf_field() ?>
                    <input type="submit" class="btn checkout-btn" value="Checkout" name="checkout" />
                </form>
            </div>
        <?php else: ?>
            <div style="margin-top: 24px; text-align: right; color: #888; font-style: italic;">
                Fill in and save details for all <?= $totalQty ?> recipient<?= $totalQty === 1 ? '' : 's' ?>
                to continue to checkout.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
