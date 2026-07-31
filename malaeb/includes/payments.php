<?php
// ============================================================
//  includes/payments.php — talking to Moyasar.
//
//  Two functions the pages use:
//    moyasar_create_invoice() -> returns a URL to send the customer to
//    moyasar_fetch_payment()  -> reads a payment back to verify it PAID
//
//  The golden rule of gateway integration: never trust the browser's
//  redirect about whether money moved. We always read the payment back
//  from Moyasar's API and check the status and the amount ourselves.
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

// Moyasar (like Stripe) counts money in the smallest unit. 1 SAR = 100
// halalas, and the amount must be a whole number of halalas.
function payment_amount_halalas($sar): int
{
    return (int) round(((float) $sar) * 100);
}

// Build an absolute https URL back to one of our own pages. Moyasar needs a
// full URL to redirect the customer to after payment.
function payment_url_to(string $page, array $query = []): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $qs     = $query ? ('?' . http_build_query($query)) : '';
    return $scheme . '://' . $host . $dir . '/' . $page . $qs;
}

// Low-level authenticated request to the Moyasar API. Basic auth: the secret
// key is the username, the password is blank.
function moyasar_request(string $method, string $path, array $data = []): array
{
    $ch = curl_init(MOYASAR_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERPWD        => MOYASAR_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body   = curl_exec($ch);
    $errno  = curl_errno($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        error_log('Moyasar request failed: ' . $errno);
        return ['_ok' => false, '_error' => 'network'];
    }
    $decoded = json_decode($body, true) ?: [];
    $decoded['_ok']     = $status >= 200 && $status < 300;
    $decoded['_status'] = $status;
    return $decoded;
}

// Create a hosted invoice and return ['ok'=>bool, 'url'=>..., 'id'=>...].
// In simulate mode we skip Moyasar entirely and point the customer at our own
// local test-checkout page, so the flow is identical minus the real charge.
function moyasar_create_invoice(int $amountHalalas, string $description, string $callbackUrl, int $bookingId): array
{
    if (PAYMENTS_MODE !== 'live') {
        return [
            'ok'  => true,
            'id'  => 'sim_' . $bookingId,
            'url' => payment_url_to('pay.php', ['booking_id' => $bookingId, 'checkout' => 1]),
        ];
    }

    $res = moyasar_request('POST', '/invoices', [
        'amount'       => $amountHalalas,
        'currency'     => PAYMENT_CURRENCY,
        'description'  => $description,
        'callback_url' => $callbackUrl,
    ]);

    if (!($res['_ok'] ?? false) || empty($res['url'])) {
        return ['ok' => false];
    }
    return ['ok' => true, 'id' => $res['id'] ?? '', 'url' => $res['url']];
}

// Read a payment back and decide if it truly paid the right amount.
// Returns ['paid'=>bool, 'id'=>string].
function moyasar_verify_payment(string $paymentId, int $expectedHalalas): array
{
    if (PAYMENTS_MODE !== 'live') {
        // In simulate mode the "payment id" carries the outcome the test
        // checkout chose, e.g. sim_paid_37 or sim_failed_37.
        $paid = strpos($paymentId, 'sim_paid_') === 0;
        return ['paid' => $paid, 'id' => $paymentId];
    }

    $res = moyasar_request('GET', '/payments/' . urlencode($paymentId));
    $paid = ($res['_ok'] ?? false)
         && ($res['status'] ?? '') === 'paid'
         && (int) ($res['amount'] ?? 0) === $expectedHalalas
         && strtoupper($res['currency'] ?? '') === PAYMENT_CURRENCY;

    return ['paid' => $paid, 'id' => $paymentId];
}
