<?php
// server/oxapay.php - OxaPay SDK wrapper.
// Requires: composer require oxapay/oxapay-php
//
// Provides the four capabilities we need:
//   - Generate invoice (oxapay_create_invoice)
//   - Verify + read webhook (oxapay_webhook_get_data)
//   - Look up a single payment (oxapay_payment_info)
//   - List payment history (oxapay_payment_history)

require_once __DIR__ . '/connection.php';

$__autoload = __DIR__ . '/../vendor/autoload.php';
if (is_readable($__autoload)) {
    require_once $__autoload;
}

use OxaPay\PHP\Support\Facades\OxaPay;
use OxaPay\PHP\Exceptions\OxaPayException;
use OxaPay\PHP\Exceptions\WebhookSignatureException;

/** Throws if the SDK isn't installed or the merchant key is unset. */
function _oxapay_require_ready(): void {
    global $OXAPAY_MERCHANT_KEY;
    if (!class_exists(OxaPay::class)) {
        throw new RuntimeException(
            'OxaPay SDK is not installed. Run: composer require oxapay/oxapay-php'
        );
    }
    if ($OXAPAY_MERCHANT_KEY === '') {
        throw new RuntimeException('OXAPAY_MERCHANT_KEY is not configured.');
    }
}

/**
 * Pull useful fields out of an SDK response whatever shape it takes.
 * v1 API wraps results in { data: {...} }; some SDK versions unwrap that.
 */
function _oxapay_unwrap_data(mixed $res): array {
    if (!is_array($res)) return [];
    if (isset($res['data']) && is_array($res['data'])) {
        // Keep top-level keys alongside the unwrapped data for status/message access.
        return array_merge($res, $res['data']);
    }
    return $res;
}

/**
 * Create an OxaPay invoice. Returns a normalized array containing at least
 * 'pay_link' (the URL to redirect the customer to) and 'track_id'.
 * Throws RuntimeException on failure.
 *
 * Field-name compatibility: OxaPay's v1 API returns the URL as
 * `payment_url`; older SDK docs referred to it as `pay_link`. We accept
 * either and always set both in the returned array so callers can use
 * whichever they prefer.
 *
 * @param array $payload  Fields: amount, currency, lifetime, order_id, email,
 *                        description, callback_url, return_url
 */
function oxapay_create_invoice(array $payload): array {
    global $OXAPAY_MERCHANT_KEY;
    _oxapay_require_ready();

    if (isset($payload['amount'])) {
        $payload['amount'] = (float)$payload['amount'];
    }

    try {
        $res = OxaPay::payment($OXAPAY_MERCHANT_KEY)->generateInvoice($payload);
    } catch (OxaPayException $e) {
        throw new RuntimeException('OxaPay generateInvoice failed: ' . $e->getMessage(), 0, $e);
    }

    $data = _oxapay_unwrap_data($res);

    // Normalise the URL field. v1 uses payment_url; legacy used pay_link.
    $url = $data['payment_url'] ?? $data['pay_link'] ?? '';
    if ($url === '') {
        throw new RuntimeException(
            'OxaPay did not return a payment URL. Raw response: ' . json_encode($res)
        );
    }
    $data['payment_url'] = $url;
    $data['pay_link']    = $url; // alias for backward compatibility

    return $data;
}

/**
 * Validate the incoming webhook and return the decoded JSON payload.
 * The SDK reads php://input itself and verifies the HMAC-SHA512 signature
 * against the 'HMAC' header. Throws RuntimeException on bad signature or
 * other SDK errors.
 */
function oxapay_webhook_get_data(): array {
    global $OXAPAY_MERCHANT_KEY;
    _oxapay_require_ready();
    try {
        $data = OxaPay::webhook(merchantApiKey: $OXAPAY_MERCHANT_KEY)->getData();
    } catch (WebhookSignatureException $e) {
        throw new RuntimeException('Webhook signature invalid: ' . $e->getMessage(), 0, $e);
    } catch (OxaPayException $e) {
        throw new RuntimeException('Webhook processing failed: ' . $e->getMessage(), 0, $e);
    }
    return is_array($data) ? $data : [];
}

/**
 * Normalize OxaPay status strings. OxaPay's v1 API uses capitalised values
 * (Waiting, Paying, Paid, Expired, Failed); we lowercase + bucket.
 *   -> 'paying' | 'paid' | 'failed' | 'expired' | 'unknown'
 */
function oxapay_normalize_status(?string $status): string {
    $s = strtolower(trim((string)$status));
    return match ($s) {
        'paid', 'success', 'completed' => 'paid',
        'paying', 'confirming', 'waiting' => 'paying',
        'failed', 'cancelled', 'canceled', 'rejected' => 'failed',
        'expired' => 'expired',
        default => 'unknown',
    };
}

/**
 * Fetch live information about a single invoice/payment from OxaPay.
 * Returns the unwrapped data block from the API response.
 */
function oxapay_payment_info(string $trackId): array {
    global $OXAPAY_MERCHANT_KEY;
    _oxapay_require_ready();
    $trackId = trim($trackId);
    if ($trackId === '') {
        throw new RuntimeException('track_id is required.');
    }
    try {
        $res = OxaPay::payment($OXAPAY_MERCHANT_KEY)->information(['track_id' => $trackId]);
    } catch (OxaPayException $e) {
        throw new RuntimeException('OxaPay information failed: ' . $e->getMessage(), 0, $e);
    }
    return _oxapay_unwrap_data($res);
}

/**
 * Fetch the payment history from OxaPay. $params can include:
 *   size (per-page, default 20), page (default 1), from_date, to_date,
 *   status, type, currency, order_by, etc. See OxaPay docs for full list.
 *
 * Returns an array with 'list' (array of payments) and 'meta' (paging info).
 */
function oxapay_payment_history(array $params = []): array {
    global $OXAPAY_MERCHANT_KEY;
    _oxapay_require_ready();
    if (!isset($params['size'])) $params['size'] = 20;
    if (!isset($params['page'])) $params['page'] = 1;
    try {
        $res = OxaPay::payment($OXAPAY_MERCHANT_KEY)->history($params);
    } catch (OxaPayException $e) {
        throw new RuntimeException('OxaPay history failed: ' . $e->getMessage(), 0, $e);
    }
    return _oxapay_unwrap_data($res);
}