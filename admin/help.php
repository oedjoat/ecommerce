<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Help</h1>
            </div>

            <h3>Quick Reference</h3>
            <ul>
                <li><strong>Dashboard / Orders</strong> &mdash; view, edit and delete customer orders.
                    Each order now records the chosen <em>payment method</em> (PayPal or OxaPay),
                    coupon used, discount amount, tier unit price, and total quantity.</li>
                <li><strong>Products</strong> &mdash; manage existing products. Use Edit / Edit Images / Delete per row.
                    Note: storefront pricing is volume-based, so the per-product <code>product_price</code>
                    is informational only (it is overridden at checkout by the cart-quantity tier).</li>
                <li><strong>Add New Product</strong> &mdash; all four images are required (JPEG, PNG, or WebP under 4&nbsp;MB each).</li>
                <li><strong>Coupons</strong> &mdash; create discount codes (percent or fixed),
                    set min-order, total-use cap, per-user cap, and expiry. Toggle <em>Active</em>
                    to disable a code without deleting it.</li>
                <li><strong>Account</strong> &mdash; view your admin profile.</li>
                <li><strong>Sign out</strong> &mdash; top-right corner of the navbar.</li>
            </ul>

            <h3 class="mt-4">Pricing tiers</h3>
            <p>Unit price is set by total cart quantity:</p>
            <ul>
                <li>1 item &mdash; $120</li>
                <li>2&ndash;4 items &mdash; $105</li>
                <li>5&ndash;10 items &mdash; $95</li>
                <li>11&ndash;20 items &mdash; $85</li>
                <li>21&ndash;30 items &mdash; $70</li>
                <li>31&ndash;50 items &mdash; $65</li>
                <li>51+ items &mdash; $60</li>
            </ul>
            <p>To change the tiers, edit <code>pricing_tiers()</code> in <code>config.php</code> &mdash;
                it's the single source of truth.</p>

            <h3 class="mt-4">Payment providers</h3>
            <p>Customers can choose PayPal or OxaPay (crypto) on the payment page.
                Both record into the <code>payments</code> table with the <code>provider</code> column.
                OxaPay relies on a webhook callback to confirm payment &mdash; ensure
                <code>APP_BASE_URL</code> is set to a public HTTPS URL.</p>

            <h3 class="mt-4">Need more help?</h3>
            <p>Contact <a href="mailto:support@example.com">support@example.com</a>.</p>
        </main>
    </div>
</div>
</body>
</html>
