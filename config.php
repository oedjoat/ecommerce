<?php
// config.php - Central configuration, secure session bootstrap, and helpers.
// Included by every entry point BEFORE any output.

// ---- Error reporting (disable display in production) ----------------------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- Load .env if present -------------------------------------------------
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        // Strip surrounding quotes so multi-word values can be quoted in .env
        if (strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// ---- DB ------------------------------------------------------------------
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'php_project';

// ---- PayPal --------------------------------------------------------------
$PAYPAL_CLIENT_ID = getenv('PAYPAL_CLIENT_ID') ?: '';
$PAYPAL_CURRENCY  = getenv('PAYPAL_CURRENCY')  ?: 'USD';

// ---- OxaPay --------------------------------------------------------------
$OXAPAY_MERCHANT_KEY = getenv('OXAPAY_MERCHANT_KEY') ?: '';
$OXAPAY_CURRENCY     = getenv('OXAPAY_CURRENCY')     ?: 'USD';
$OXAPAY_SANDBOX      = (getenv('OXAPAY_SANDBOX') === '1');
// Fully-qualified, public base URL for callbacks/return URLs.
// e.g. https://shop.example.com - OxaPay cannot reach localhost.
$APP_BASE_URL        = rtrim((string)(getenv('APP_BASE_URL') ?: ''), '/');

// ---- SMTP / Mail ---------------------------------------------------------
$SMTP_HOST       = getenv('SMTP_HOST')       ?: '';
$SMTP_PORT       = (int)(getenv('SMTP_PORT') ?: 587);
$SMTP_USER       = getenv('SMTP_USER')       ?: '';
$SMTP_PASS       = getenv('SMTP_PASS')       ?: '';
$SMTP_ENCRYPTION = getenv('SMTP_ENCRYPTION') ?: 'tls'; // 'tls', 'ssl', or ''
$MAIL_FROM_ADDR  = getenv('MAIL_FROM_ADDR')  ?: 'no-reply@example.com';
$MAIL_FROM_NAME  = getenv('MAIL_FROM_NAME')  ?: 'Kimmi Shop';
$ADMIN_EMAIL     = getenv('ADMIN_EMAIL')     ?: '';

// ---- Shipping ------------------------------------------------------------
const SHIPPING_FEE = 20.00;

// ---- Secure session settings (must be set BEFORE session_start) ----------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('ECOMSESSID');
    session_start();
}

// ---- Helpers --------------------------------------------------------------

/** Escape output for HTML context. */
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate / fetch a CSRF token for the current session. */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Render a hidden CSRF input. */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Validate POSTed CSRF token. Aborts with 403 on failure. */
function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || empty($_SESSION['_csrf'])
        || !hash_equals($_SESSION['_csrf'], $sent)) {
        http_response_code(403);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

/** Regenerate session ID after privilege change (login/logout). */
function session_rotate(): void {
    session_regenerate_id(true);
}

/** Safely read a flash query string (still escape with e() on output). */
function flash(string $key): ?string {
    return isset($_GET[$key]) ? (string)$_GET[$key] : null;
}

// =============================================================================
// TIERED PRICING - based on TOTAL cart quantity, overrides product prices.
// =============================================================================

/**
 * Pricing tiers: total cart quantity -> unit price applied to every unit.
 * Ordered ascending by quantity; first matching tier wins.
 * Edit this single source of truth to change pricing.
 */
function pricing_tiers(): array {
    return [
        ['min' => 1,  'max' => 1,    'price' => 120.00],
        ['min' => 2,  'max' => 4,    'price' => 105.00],
        ['min' => 5,  'max' => 10,   'price' => 95.00],
        ['min' => 11, 'max' => 20,   'price' => 85.00],
        ['min' => 21, 'max' => 30,   'price' => 70.00],
        ['min' => 31, 'max' => 50,   'price' => 65.00],
        ['min' => 51, 'max' => PHP_INT_MAX, 'price' => 60.00],
    ];
}

/** Unit price for a given total cart quantity. Returns 0.0 when qty <= 0. */
function tier_unit_price(int $totalQty): float {
    if ($totalQty <= 0) return 0.0;
    foreach (pricing_tiers() as $tier) {
        if ($totalQty >= $tier['min'] && $totalQty <= $tier['max']) {
            return (float)$tier['price'];
        }
    }
    return 0.0;
}

/** Sum total quantity across the session cart. */
function cart_total_quantity(): int {
    $q = 0;
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $q += max(0, (int)($item['product_quantity'] ?? 0));
        }
    }
    return $q;
}

// =============================================================================
// COUPONS
// =============================================================================

/** Fetch active coupon by code, or null. */
function coupon_find_by_code(mysqli $conn, string $code): ?array {
    $code = trim($code);
    if ($code === '') return null;
    $stmt = $conn->prepare(
        "SELECT coupon_id, code, discount_type, discount_value,
                min_order, max_uses, times_used, per_user_limit, expires_at, active
         FROM coupons WHERE code=? LIMIT 1"
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Validate a coupon against a subtotal and user. Returns:
 *   ['ok' => bool, 'error' => ?string, 'discount' => float, 'coupon' => ?array]
 */
function coupon_validate(mysqli $conn, ?array $coupon, float $subtotal, int $user_id): array {
    if (!$coupon) {
        return ['ok' => false, 'error' => 'Coupon not found.', 'discount' => 0.0, 'coupon' => null];
    }
    if ((int)$coupon['active'] !== 1) {
        return ['ok' => false, 'error' => 'Coupon is not active.', 'discount' => 0.0, 'coupon' => $coupon];
    }
    if (!empty($coupon['expires_at']) && strtotime((string)$coupon['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'Coupon has expired.', 'discount' => 0.0, 'coupon' => $coupon];
    }
    if ($subtotal < (float)$coupon['min_order']) {
        $msg = 'Minimum order of $' . number_format((float)$coupon['min_order'], 2) . ' required.';
        return ['ok' => false, 'error' => $msg, 'discount' => 0.0, 'coupon' => $coupon];
    }
    if ($coupon['max_uses'] !== null && (int)$coupon['times_used'] >= (int)$coupon['max_uses']) {
        return ['ok' => false, 'error' => 'Coupon usage limit reached.', 'discount' => 0.0, 'coupon' => $coupon];
    }
    if ($coupon['per_user_limit'] !== null && $user_id > 0) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id=? AND user_id=?"
        );
        $cid = (int)$coupon['coupon_id'];
        $stmt->bind_param('ii', $cid, $user_id);
        $stmt->execute();
        $stmt->bind_result($used_by_user);
        $stmt->fetch();
        $stmt->close();
        if ((int)$used_by_user >= (int)$coupon['per_user_limit']) {
            return ['ok' => false, 'error' => 'You have already redeemed this coupon.', 'discount' => 0.0, 'coupon' => $coupon];
        }
    }
    $discount = coupon_compute_discount($coupon, $subtotal);
    return ['ok' => true, 'error' => null, 'discount' => $discount, 'coupon' => $coupon];
}

/** Calculate the discount amount; never exceeds the subtotal. */
function coupon_compute_discount(array $coupon, float $subtotal): float {
    if ($subtotal <= 0) return 0.0;
    $type  = (string)$coupon['discount_type'];
    $value = (float)$coupon['discount_value'];
    $d = $type === 'percent' ? $subtotal * ($value / 100.0) : $value;
    if ($d < 0) $d = 0.0;
    if ($d > $subtotal) $d = $subtotal;
    return round($d, 2);
}

/** Remember the applied coupon code in the session. */
function coupon_apply_session(string $code): void {
    $_SESSION['coupon_code'] = strtoupper(trim($code));
}

/** Forget any session coupon. */
function coupon_clear_session(): void {
    unset($_SESSION['coupon_code']);
}

/** Fetch the currently-applied session coupon row (or null). */
function coupon_get_applied(mysqli $conn): ?array {
    if (empty($_SESSION['coupon_code'])) return null;
    return coupon_find_by_code($conn, (string)$_SESSION['coupon_code']);
}
