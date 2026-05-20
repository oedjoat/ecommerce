<?php
// server/mailer.php - PHPMailer wrapper. Requires: composer require phpmailer/phpmailer

require_once __DIR__ . '/connection.php';

$__autoload = __DIR__ . '/../vendor/autoload.php';
if (is_readable($__autoload)) {
    require_once $__autoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function send_mail(string $toAddr, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_ENCRYPTION,
           $MAIL_FROM_ADDR, $MAIL_FROM_NAME;

    if (!class_exists(PHPMailer::class)) {
        error_log('PHPMailer is not installed. Run: composer require phpmailer/phpmailer');
        return false;
    }
    if ($SMTP_HOST === '' || $toAddr === '') {
        error_log('SMTP not configured or empty recipient; skipping email to ' . $toAddr);
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->Port       = $SMTP_PORT;
        $mail->SMTPAuth   = ($SMTP_USER !== '' || $SMTP_PASS !== '');
        if ($mail->SMTPAuth) {
            $mail->Username = $SMTP_USER;
            $mail->Password = $SMTP_PASS;
        }
        if ($SMTP_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($SMTP_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($MAIL_FROM_ADDR, $MAIL_FROM_NAME);
        $mail->addAddress($toAddr, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log('Email send failed to ' . $toAddr . ': ' . $mail->ErrorInfo);
        return false;
    } catch (Throwable $t) {
        error_log('Email send failed to ' . $toAddr . ': ' . $t->getMessage());
        return false;
    }
}

function render_order_items_html(array $items): string {
    $rows = '';
    foreach ($items as $li) {
        $name = htmlspecialchars((string)$li['product_name'], ENT_QUOTES, 'UTF-8');
        $qty  = (int)$li['product_quantity'];
        $unit = (float)$li['product_price'];
        $sub  = $unit * $qty;
        $rows .= '<tr>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . $name . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:center;">' . $qty . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">$' . number_format($unit, 2) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">$' . number_format($sub, 2) . '</td>'
            . '</tr>';
    }
    return '<table style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:14px;">
        <thead><tr style="background:#fb774b;color:#fff;">
            <th style="padding:8px 10px;text-align:left;">Product</th>
            <th style="padding:8px 10px;text-align:center;">Qty</th>
            <th style="padding:8px 10px;text-align:right;">Unit</th>
            <th style="padding:8px 10px;text-align:right;">Line Total</th>
        </tr></thead><tbody>' . $rows . '</tbody></table>';
}

function render_order_items_text(array $items): string {
    $out = '';
    foreach ($items as $li) {
        $out .= sprintf("  %-40s  x%-3d  $%7.2f\n",
            mb_strimwidth((string)$li['product_name'], 0, 40, '..'),
            (int)$li['product_quantity'],
            (float)$li['product_price'] * (int)$li['product_quantity']
        );
    }
    return $out;
}

/** Per-recipient labeled block; renders every field returned by recipient_field_names(). */
function render_recipients_html(array $recipients): string {
    if (empty($recipients)) return '';
    $fields = recipient_field_names();
    $out = '';
    foreach ($recipients as $i => $r) {
        $idx = (int)$i + 1;
        $rows = '';
        foreach ($fields as $f) {
            $rows .= '<tr>'
                . '<td style="padding:4px 8px;color:#555;width:90px;border-bottom:1px solid #f3f3f3;">'
                . htmlspecialchars((string)$f, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #f3f3f3;">'
                . htmlspecialchars((string)($r[$f] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
        $out .= '<div style="border:1px solid #e3e3e3;border-radius:4px;padding:10px 14px;margin-bottom:10px;">'
              . '<div style="font-weight:600;margin-bottom:4px;">Recipient #' . $idx . '</div>'
              . '<table style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:13px;">'
              . $rows
              . '</table></div>';
    }
    return $out;
}

function render_recipients_text(array $recipients): string {
    if (empty($recipients)) return '';
    $fields = recipient_field_names();
    $out = "Recipients:\n";
    foreach ($recipients as $i => $r) {
        $idx = (int)$i + 1;
        $out .= "  #$idx\n";
        foreach ($fields as $f) {
            $v = (string)($r[$f] ?? '');
            $out .= "    $f: $v\n";
        }
    }
    return $out;
}

function send_order_placed_emails(array $order, array $items): void {
    global $ADMIN_EMAIL;

    $oid        = (int)$order['order_id'];
    $name       = (string)$order['user_name'];
    $email      = (string)$order['user_email'];
    $phone      = (string)$order['user_phone'];
    $city       = (string)$order['user_city'];
    $address    = (string)$order['user_address'];
    $subtotal   = (float)$order['subtotal'];
    $shipping   = (float)$order['shipping'];
    $discount   = (float)$order['discount_amount'];
    $total      = (float)$order['order_cost'];
    $tierPrice  = (float)$order['tier_unit_price'];
    $totalQty   = (int)$order['total_quantity'];
    $couponCode = isset($order['coupon_code']) ? (string)$order['coupon_code'] : '';
    $recipients = isset($order['recipients']) && is_array($order['recipients'])
                    ? $order['recipients'] : [];

    $itemsHtml      = render_order_items_html($items);
    $itemsText      = render_order_items_text($items);
    $recipientsHtml = render_recipients_html($recipients);
    $recipientsText = render_recipients_text($recipients);

    $couponLine = $couponCode !== ''
        ? '<tr><td>Coupon (' . htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8') . ')</td><td style="text-align:right;">-$' . number_format($discount, 2) . '</td></tr>'
        : '';

    $billingBlock = '
        <p style="margin:0 0 4px 0;"><strong>Billing contact:</strong></p>
        <p style="margin:0;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '<br>'
        . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '<br>'
        . htmlspecialchars($city, ENT_QUOTES, 'UTF-8') . '<br>'
        . 'Phone: ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</p>';

    $totalsTable = '
        <table style="margin-top:14px;width:100%;font-family:Arial,sans-serif;font-size:14px;">
            <tr><td>Tier unit price (x' . $totalQty . ' items)</td><td style="text-align:right;">$' . number_format($tierPrice, 2) . '</td></tr>
            <tr><td>Subtotal</td><td style="text-align:right;">$' . number_format($subtotal, 2) . '</td></tr>
            ' . $couponLine . '
            <tr><td>Shipping</td><td style="text-align:right;">$' . number_format($shipping, 2) . '</td></tr>
            <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong>$' . number_format($total, 2) . '</strong></td></tr>
        </table>';

    $recipientsSection = $recipientsHtml === '' ? '' : '
        <h3 style="margin-top:20px;font-size:16px;">Recipients (' . count($recipients) . ')</h3>
        ' . $recipientsHtml;

    $customerHtml = '
        <div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;">
            <h2 style="color:#fb774b;">Thanks for your order, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!</h2>
            <p>Your order <strong>#' . $oid . '</strong> has been received. Please complete payment to confirm it.</p>
            ' . $itemsHtml . '
            ' . $totalsTable . '
            ' . $recipientsSection . '
            <div style="margin-top:18px;">' . $billingBlock . '</div>
            <p style="margin-top:20px;color:#888;font-size:12px;">If you have any questions, just reply to this email.</p>
        </div>';
    $customerText = "Thanks for your order, $name!\n\nOrder #$oid\n\n" . $itemsText .
        "\nSubtotal: $" . number_format($subtotal, 2) .
        ($couponCode !== '' ? "\nCoupon ($couponCode): -$" . number_format($discount, 2) : '') .
        "\nShipping: $" . number_format($shipping, 2) .
        "\nTotal: $" . number_format($total, 2) . "\n\n" .
        $recipientsText .
        "\nBilling: $name, $address, $city. Phone: $phone\n";

    send_mail($email, $name, 'Order #' . $oid . ' received', $customerHtml, $customerText);

    if ($ADMIN_EMAIL !== '') {
        $adminHtml = '
            <div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;">
                <h2>New order #' . $oid . '</h2>
                <p>Customer: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' &lt;' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '&gt;</p>
                ' . $itemsHtml . '
                ' . $totalsTable . '
                ' . $recipientsSection . '
                <div style="margin-top:18px;">' . $billingBlock . '</div>
            </div>';
        send_mail($ADMIN_EMAIL, 'Admin', '[Kimmi] New order #' . $oid . ' - $' . number_format($total, 2),
                  $adminHtml, "New order #$oid from $name <$email>\nTotal: $" . number_format($total, 2) . "\n\n" . $recipientsText);
    }
}

function send_payment_confirmed_emails(array $order, string $provider, string $transactionId): void {
    global $ADMIN_EMAIL;

    $oid     = (int)$order['order_id'];
    $name    = (string)$order['user_name'];
    $email   = (string)$order['user_email'];
    $total   = (float)$order['order_cost'];

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;">
            <h2 style="color:#0a8a3a;">Payment received - thank you!</h2>
            <p>We have received your payment of <strong>$' . number_format($total, 2) . '</strong> for order <strong>#' . $oid . '</strong>.</p>
            <p>Payment method: ' . htmlspecialchars(ucfirst($provider), ENT_QUOTES, 'UTF-8') . '<br>
               Transaction reference: ' . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') . '</p>
            <p>We will start preparing your order for shipment shortly.</p>
        </div>';
    send_mail($email, $name, 'Payment received - Order #' . $oid, $html);

    if ($ADMIN_EMAIL !== '') {
        $adminHtml = '<p>Payment received for order <strong>#' . $oid . '</strong>'
            . ' from ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' &lt;'
            . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '&gt;<br>'
            . 'Amount: $' . number_format($total, 2) . '<br>'
            . 'Method: ' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Txn: ' . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') . '</p>';
        send_mail($ADMIN_EMAIL, 'Admin', '[Kimmi] Payment received for order #' . $oid, $adminHtml);
    }
}
