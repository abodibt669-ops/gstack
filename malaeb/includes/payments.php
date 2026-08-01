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

// Work out the site's own base address, e.g. https://wagti.example.com/wagti.
// Prefers the configured WAGTI_BASE_URL; only falls back to the request's own
// host when that is unset, which is the local-XAMPP case.
function payment_base_url(): string
{
    if (WAGTI_BASE_URL !== '') {
        // Guard against a typo'd setting silently producing broken callbacks:
        // it has to be an absolute http(s) URL or we ignore it and say so.
        $parts = parse_url(WAGTI_BASE_URL);
        if (!empty($parts['scheme']) && !empty($parts['host'])
            && in_array($parts['scheme'], ['http', 'https'], true)) {
            return WAGTI_BASE_URL;
        }
        error_log('WAGTI_BASE_URL is not an absolute http(s) URL; falling back to the request host.');
    }

    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return $scheme . '://' . $host . $dir;
}

// Build an absolute URL back to one of our own pages. Moyasar needs a full URL
// to redirect the customer to after payment.
function payment_url_to(string $page, array $query = []): string
{
    $qs = $query ? ('?' . http_build_query($query)) : '';
    return payment_base_url() . '/' . $page . $qs;
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

// Decide whether a payment we read back from Moyasar really pays for THIS
// booking. Kept separate from the network call so the decision can be tested
// directly, without a Moyasar account or a live payment to hand.
//
// Every condition has to hold. In particular the payment must belong to the
// invoice we created for this booking: without that check, the payment id is
// just a number in the callback URL, and anyone holding the id of any other
// successful payment for the same amount could hand it to us and have their
// own booking confirmed for free.
//
// Returns ['paid'=>bool, 'reason'=>string]. The reason names the first thing
// that failed, so a rejection can be logged as something specific rather than
// a shrug.
function moyasar_payment_is_acceptable(array $payment, int $expectedHalalas, string $expectedInvoiceId): array
{
    if (!($payment['_ok'] ?? false)) {
        return ['paid' => false, 'reason' => 'lookup_failed'];
    }
    if (($payment['status'] ?? '') !== 'paid') {
        return ['paid' => false, 'reason' => 'not_paid'];
    }
    if ((int) ($payment['amount'] ?? 0) !== $expectedHalalas) {
        return ['paid' => false, 'reason' => 'amount_mismatch'];
    }
    if (strtoupper($payment['currency'] ?? '') !== PAYMENT_CURRENCY) {
        return ['paid' => false, 'reason' => 'currency_mismatch'];
    }
    // Fail closed: no invoice recorded for the booking, or none on the payment,
    // means we cannot prove the two belong together, so we do not confirm.
    if ($expectedInvoiceId === '' || ($payment['invoice_id'] ?? '') === '') {
        return ['paid' => false, 'reason' => 'unbound_payment'];
    }
    if (!hash_equals($expectedInvoiceId, (string) $payment['invoice_id'])) {
        return ['paid' => false, 'reason' => 'wrong_invoice'];
    }
    return ['paid' => true, 'reason' => 'ok'];
}

// Read a payment back and decide if it truly paid for this booking.
// $expectedInvoiceId is the booking's stored invoice_ref, written by pay.php
// when the invoice was created.
// Returns ['paid'=>bool, 'id'=>string, 'reason'=>string].
function moyasar_verify_payment(string $paymentId, int $expectedHalalas, string $expectedInvoiceId = ''): array
{
    if (PAYMENTS_MODE !== 'live') {
        // In simulate mode the "payment id" carries the outcome the test
        // checkout chose, e.g. sim_paid_37 or sim_failed_37.
        $paid = strpos($paymentId, 'sim_paid_') === 0;
        return ['paid' => $paid, 'id' => $paymentId, 'reason' => $paid ? 'ok' : 'not_paid'];
    }

    if ($paymentId === '') {
        return ['paid' => false, 'id' => '', 'reason' => 'no_payment_id'];
    }

    $res     = moyasar_request('GET', '/payments/' . urlencode($paymentId));
    $verdict = moyasar_payment_is_acceptable($res, $expectedHalalas, $expectedInvoiceId);

    return ['paid' => $verdict['paid'], 'id' => $paymentId, 'reason' => $verdict['reason']];
}
