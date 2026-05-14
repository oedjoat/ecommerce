<?php
require_once __DIR__ . '/server/connection.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$order_id   = 0;
$amount     = 0.00;
$can_pay    = false;
$status_msg = '';

// Determine which order we're paying for and recompute amount from DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_pay_btn'])) {
    csrf_check();
    $order_id = (int)($_POST['order_id'] ?? 0);
} elseif (!empty($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
} elseif (!empty($_SESSION['order_id'])) {
    $order_id = (int)$_SESSION['order_id'];
}

if ($order_id > 0) {
    $stmt = $conn->prepare(
        "SELECT order_id, order_cost, order_status
         FROM orders WHERE order_id=? AND user_id=? LIMIT 1"
    );
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order && $order['order_status'] === 'not paid') {
        $amount  = (float)$order['order_cost'];
        $can_pay = true;
    } elseif ($order) {
        $status_msg = 'This order has already been paid.';
    } else {
        $status_msg = 'Order not found.';
    }
}

$oxa_error = !empty($_GET['oxa_error']) ? (string)$_GET['oxa_error'] : '';

include __DIR__ . '/layouts/header.php';
?>

    <!-- PAYMENT -->
    <section class="my-5 py-5">
        <div class="container text-center mt-3 pt-5">
            <h2 class="form-weight-bold">Payment</h2>
            <hr class="mx-auto">
        </div>

        <div class="mx-auto container text-center" style="max-width: 540px;">
            <?php if ($can_pay): ?>
                <p>Total payment: <strong>$<?= e(number_format($amount, 2)) ?></strong></p>

                <?php if ($oxa_error): ?>
                    <p style="color:red;"><?= e($oxa_error) ?></p>
                <?php endif; ?>

                <!-- ===== Pay with crypto (OxaPay) ===== -->
                <div style="margin: 24px 0;">
                    <?php if ($OXAPAY_MERCHANT_KEY !== ''): ?>
                        <form method="POST" action="server/oxapay_create.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= e((string)$order_id) ?>" />
                            <button type="submit" class="btn"
                                    style="background:#1d1d1d;color:#fff;padding:12px 24px;width:100%;">
                                Pay with Crypto (OxaPay)
                            </button>
                        </form>
                        <p style="font-size:12px;color:#888;margin-top:6px;">
                            BTC, ETH, USDT, and more. You will be redirected to OxaPay.
                        </p>
                    <?php else: ?>
                        <p style="color:#888;">Crypto payment is not configured.</p>
                    <?php endif; ?>
                </div>

                <div style="display:flex;align-items:center;gap:10px;color:#aaa;margin:14px 0;">
                    <hr style="flex:1;height:1px;background:#ddd;border:0;">
                    <span>or</span>
                    <hr style="flex:1;height:1px;background:#ddd;border:0;">
                </div>

                <!-- ===== Pay with PayPal ===== -->
                <div id="paypal-button-container" class="justify-content"></div>
                <p id="result-message"></p>

                <?php if ($PAYPAL_CLIENT_ID === ''): ?>
                    <p style="color:#888;">PayPal is not configured.</p>
                <?php endif; ?>
            <?php else: ?>
                <p><?= e($status_msg ?: 'Nothing to pay for. Please add something to your cart.') ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($can_pay && $PAYPAL_CLIENT_ID !== ''): ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?= rawurlencode($PAYPAL_CLIENT_ID) ?>&currency=<?= rawurlencode($PAYPAL_CURRENCY) ?>&components=buttons&enable-funding=venmo,card&disable-funding=paylater"
                data-sdk-integration-source="developer-studio"></script>
        <script>
            (function () {
                var amount  = <?= json_encode(number_format($amount, 2, '.', ''), JSON_UNESCAPED_SLASHES) ?>;
                var orderId = <?= json_encode((string)$order_id) ?>;

                paypal.Buttons({
                    style: { shape: "pill", layout: "vertical", color: "gold", label: "pay" },
                    createOrder: function (data, actions) {
                        return actions.order.create({
                            purchase_units: [{ amount: { value: amount } }]
                        });
                    },
                    onApprove: function (data, actions) {
                        return actions.order.capture().then(function (orderData) {
                            var transaction = orderData.purchase_units[0].payments.captures[0];
                            window.location.href = "server/complete_payment.php"
                                + "?transaction_id=" + encodeURIComponent(transaction.id)
                                + "&order_id="       + encodeURIComponent(orderId);
                        }).catch(function (err) {
                            console.error("Capture error", err);
                            alert("There was a problem completing the payment.");
                        });
                    }
                }).render("#paypal-button-container");
            })();
        </script>
    <?php endif; ?>

<?php include __DIR__ . '/layouts/footer.php'; ?>
