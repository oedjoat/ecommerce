<?php
// config.php - Central configuration, secure session bootstrap, and helpers.

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- Load .env if present -------------------------------------------------
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
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
$PAYPAL_CLIENT_ID     = getenv('PAYPAL_CLIENT_ID')     ?: '';
$PAYPAL_CLIENT_SECRET = getenv('PAYPAL_CLIENT_SECRET') ?: '';
$PAYPAL_CURRENCY      = getenv('PAYPAL_CURRENCY')      ?: 'USD';
$PAYPAL_MODE          = (getenv('PAYPAL_MODE') ?: 'sandbox'); // 'sandbox' or 'live'

// ---- OxaPay --------------------------------------------------------------
$OXAPAY_MERCHANT_KEY = getenv('OXAPAY_MERCHANT_KEY') ?: '';
$OXAPAY_CURRENCY     = getenv('OXAPAY_CURRENCY')     ?: 'USD';
$OXAPAY_SANDBOX      = (getenv('OXAPAY_SANDBOX') === '1');
$APP_BASE_URL        = rtrim((string)(getenv('APP_BASE_URL') ?: ''), '/');

// ---- SMTP / Mail ---------------------------------------------------------
$SMTP_HOST       = getenv('SMTP_HOST')       ?: '';
$SMTP_PORT       = (int)(getenv('SMTP_PORT') ?: 587);
$SMTP_USER       = getenv('SMTP_USER')       ?: '';
$SMTP_PASS       = getenv('SMTP_PASS')       ?: '';
$SMTP_ENCRYPTION = getenv('SMTP_ENCRYPTION') ?: 'tls';
$MAIL_FROM_ADDR  = getenv('MAIL_FROM_ADDR')  ?: 'no-reply@example.com';
$MAIL_FROM_NAME  = getenv('MAIL_FROM_NAME')  ?: 'Kimmi Shop';
$ADMIN_EMAIL     = getenv('ADMIN_EMAIL')     ?: '';

// ---- Shipping ------------------------------------------------------------
const SHIPPING_FEE = 20.00;

// ---- Secure session settings ---------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_name('ECOMSESSID');
    session_start();
}

// ---- Basic helpers -------------------------------------------------------

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || empty($_SESSION['_csrf'])
        || !hash_equals($_SESSION['_csrf'], $sent)) {
        http_response_code(403);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

function session_rotate(): void { session_regenerate_id(true); }

function flash(string $key): ?string {
    return isset($_GET[$key]) ? (string)$_GET[$key] : null;
}

// =============================================================================
// TIERED PRICING
// =============================================================================

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

function tier_unit_price(int $totalQty): float {
    if ($totalQty <= 0) return 0.0;
    foreach (pricing_tiers() as $tier) {
        if ($totalQty >= $tier['min'] && $totalQty <= $tier['max']) {
            return (float)$tier['price'];
        }
    }
    return 0.0;
}

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
    return ['ok' => true, 'error' => null,
            'discount' => coupon_compute_discount($coupon, $subtotal),
            'coupon' => $coupon];
}

function coupon_compute_discount(array $coupon, float $subtotal): float {
    if ($subtotal <= 0) return 0.0;
    $type  = (string)$coupon['discount_type'];
    $value = (float)$coupon['discount_value'];
    $d = $type === 'percent' ? $subtotal * ($value / 100.0) : $value;
    if ($d < 0) $d = 0.0;
    if ($d > $subtotal) $d = $subtotal;
    return round($d, 2);
}

function coupon_apply_session(string $code): void {
    $_SESSION['coupon_code'] = strtoupper(trim($code));
}

function coupon_clear_session(): void { unset($_SESSION['coupon_code']); }

function coupon_get_applied(mysqli $conn): ?array {
    if (empty($_SESSION['coupon_code'])) return null;
    return coupon_find_by_code($conn, (string)$_SESSION['coupon_code']);
}

// =============================================================================
// RECIPIENTS - per-unit info, 19 fields per recipient.
//
// To change the schema:
//   1. Update recipient_field_names() with the new identifiers.
//   2. Update recipient_field_labels() with human-readable labels.
//   3. Update migration_recipients.sql to match.
//
// Every part of the system (form, validation, INSERT, email, Excel, account
// page) reads from these two functions, so this is the only place to edit.
// =============================================================================

function recipient_field_names(): array {
    return [
        'foo1',  'foo2',  'foo3',  'foo4',  'foo5',
        'foo6',  'foo7',  'foo8',  'foo9',  'foo10',
        'foo11', 'foo12', 'foo13', 'foo14', 'foo15',
        'foo16', 'foo17', 'foo18', 'foo19',
    ];
}

/**
 * Human-readable labels per field. Fields not listed here fall back to a
 * title-cased version of the identifier.
 */
function recipient_field_labels(): array {
    return [
        'foo1'  => 'Foo 1',
        'foo2'  => 'Foo 2',
        'foo3'  => 'Foo 3',
        'foo4'  => 'Foo 4',
        'foo5'  => 'Foo 5',
        'foo6'  => 'Foo 6',
        'foo7'  => 'Foo 7',
        'foo8'  => 'Foo 8',
        'foo9'  => 'Foo 9',
        'foo10' => 'Foo 10',
        'foo11' => 'Foo 11',
        'foo12' => 'Foo 12',
        'foo13' => 'Foo 13',
        'foo14' => 'Foo 14',
        'foo15' => 'Foo 15',
        'foo16' => 'Foo 16',
        'foo17' => 'Foo 17',
        'foo18' => 'Foo 18',
        'foo19' => 'Foo 19',
    ];
}

/** Label for one field, with humanized fallback. */
function recipient_field_label(string $field): string {
    $labels = recipient_field_labels();
    if (isset($labels[$field])) return $labels[$field];
    return ucwords(str_replace(['_', '-'], ' ', $field));
}

function recipient_field_max_length(string $field): int {
    return 255;
}

function recipients_get(): array {
    $list = $_SESSION['recipients'] ?? [];
    return is_array($list) ? $list : [];
}

function recipients_set(array $list): void {
    $_SESSION['recipients'] = array_values($list);
}

function recipients_clear(): void { unset($_SESSION['recipients']); }

function recipients_resize_to_cart(): void {
    $need = cart_total_quantity();
    if ($need === 0) { recipients_clear(); return; }
    $list = recipients_get();
    if (count($list) > $need) {
        recipients_set(array_slice($list, 0, $need));
    }
}

function recipients_complete(): bool {
    $need = cart_total_quantity();
    if ($need <= 0) return false;
    $list = recipients_get();
    if (count($list) < $need) return false;
    for ($i = 0; $i < $need; $i++) {
        $r = $list[$i] ?? [];
        foreach (['foo1', 'foo17'] as $f) {
            $v = trim((string)($r[$f] ?? ''));
            if ($v === '' || mb_strlen($v) > recipient_field_max_length($f)) return false;
        }
    }
    return true;
}
