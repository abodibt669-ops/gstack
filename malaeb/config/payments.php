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

// A pending (unpaid) booking holds its slot for this many minutes; after
// that the slot is free again so an abandoned payment can't lock it forever.
define('PENDING_HOLD_MINUTES', 15);
