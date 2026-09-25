<?php
// ============================================================
//  config/payments.php — Moyasar payment gateway settings.
//
//  Nothing secret is committed here: the keys are read from the
//  environment. On your machine you can leave them unset and the
//  gateway runs in SIMULATE mode, so you can click through the whole
//  book -> pay -> confirm flow without a Moyasar account.
//
//  When your real (test) keys arrive from the Moyasar dashboard,
//  set them in the environment and flip WAGTI_PAYMENTS_MODE to 'live':
//
//    setx WAGTI_PAYMENTS_MODE   live           (Windows)
//    setx MOYASAR_SECRET_KEY    sk_test_xxxxx
//    setx MOYASAR_PUBLISHABLE_KEY pk_test_xxxxx
//
//  Moyasar test cards (only work in test mode, never charge a real card):
//    Visa  4111 1111 1111 1111   any future expiry   CVC 123
//    3-D Secure password on the test page is: 1234
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

// 'simulate' = no account needed, a local test checkout stands in for
// Moyasar. 'live' = real API calls with your keys (still test keys until
// you go to production, which is a dashboard switch, not a code change).
define('PAYMENTS_MODE', getenv('WAGTI_PAYMENTS_MODE') ?: 'simulate');

// Read from the environment; the sk_test_/pk_test_ placeholders make it
// obvious in logs when a real key hasn't been supplied yet.
define('MOYASAR_SECRET_KEY',      getenv('MOYASAR_SECRET_KEY')      ?: 'sk_test_REPLACE_ME');
define('MOYASAR_PUBLISHABLE_KEY', getenv('MOYASAR_PUBLISHABLE_KEY') ?: 'pk_test_REPLACE_ME');

// Overridable so the live-mode path can be pointed at a stub gateway in tests.
// Without a seam like this, the code that decides whether money moved is the
// one piece that can only ever be exercised in production.
define('MOYASAR_API_BASE', getenv('MOYASAR_API_BASE') ?: 'https://api.moyasar.com/v1');
define('PAYMENT_CURRENCY', 'SAR');

// Where this site lives, e.g. https://wagti.example.com or
// https://example.com/wagti if it sits in a subfolder. The payment callback
// URL is built from this.
//
// Why it is configured rather than detected: the obvious way to work out our
// own address is $_SERVER['HTTP_HOST'], but that is just a request header, and
// the client chooses what to put in it. Someone could send a request carrying
// another site's host, and the callback URL handed to Moyasar would point
// there, sending the customer somewhere else after they paid. A value we set
// ourselves cannot be steered from outside.
//
// Left unset it falls back to the request host, which is what makes a local
// XAMPP install work with no configuration. Set it on any real host.
define('WAGTI_BASE_URL', rtrim(getenv('WAGTI_BASE_URL') ?: '', '/'));

// A real server must never take a payment it cannot trust. On production
// (MALAEB_ENV=production) payments run only when both of these hold:
//  - live mode with real keys. Otherwise the simulated checkout is live, and
//    anyone could press its test "Pay" button and get a confirmed booking
//    without any money moving.
//  - WAGTI_BASE_URL is the site's https:// address. Otherwise the callback URL
//    is built from the visitor's Host header, which a visitor can forge, so a
//    customer could be sent to someone else's site after paying.
// If either is missing, payments are switched off, every payment attempt fails
// closed, and the log says what is missing. Your own machine is unaffected.
$paymentsMissing = [];
if (getenv('MALAEB_ENV') === 'production') {
    if (PAYMENTS_MODE !== 'live'
        || strpos(MOYASAR_SECRET_KEY, 'REPLACE_ME') !== false
        || strpos(MOYASAR_PUBLISHABLE_KEY, 'REPLACE_ME') !== false) {
        $paymentsMissing[] = 'WAGTI_PAYMENTS_MODE=live and real MOYASAR_*_KEY values';
    }
    // Parsed rather than prefix-checked: a typo like "https:///wagti" starts
    // with https:// but has no host, and the callback would fall back to Host.
    $baseUrl = parse_url(WAGTI_BASE_URL) ?: [];
    if (($baseUrl['scheme'] ?? '') !== 'https' || empty($baseUrl['host'])) {
        $paymentsMissing[] = "WAGTI_BASE_URL set to the site's https:// address";
    }
}
define('PAYMENTS_AVAILABLE', $paymentsMissing === []);
foreach ($paymentsMissing as $need) {
    error_log("Wagti: payments are DISABLED. Production needs {$need}.");
}
unset($paymentsMissing, $baseUrl, $need);

// A pending (unpaid) booking holds its slot for this many minutes; after
// that the slot is free again so an abandoned payment can't lock it forever.
define('PENDING_HOLD_MINUTES', 15);
