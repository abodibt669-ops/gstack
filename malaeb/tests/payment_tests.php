<?php
// ============================================================
//  payment_tests.php — the payment decisions that money depends on.
//
//  Run it from the wagti folder:   php tests/payment_tests.php
//
//  These cover the two questions the callback has to get right in live mode:
//   1. Does this payment actually pay for THIS booking?
//   2. What address do we hand Moyasar to send the customer back to?
//
//  Neither needs a Moyasar account: the decision is a pure function over a
//  payment record, so a payment that would be rejected can be checked here
//  instead of only being discovered in production.
// ============================================================

define('MALAEB', true);
require_once __DIR__ . '/../config/payments.php';

// payment_url_to()/payment_base_url() are the only things in payments.php that
// touch the network-facing helpers; nothing here performs a request.
require_once __DIR__ . '/../includes/payments.php';

$passed = 0;
$failed = 0;

function check(string $what, $expected, $actual): void {
    global $passed, $failed;
    if ($expected === $actual) { $passed++; echo "  ok   {$what}\n"; return; }
    $failed++;
    echo "  FAIL {$what}\n";
    echo "         expected: " . var_export($expected, true) . "\n";
    echo "         actual:   " . var_export($actual, true) . "\n";
}

// A payment record shaped like Moyasar's, for a 180.00 SAR booking.
function payment(array $overrides = []): array {
    return array_merge([
        '_ok'        => true,
        'status'     => 'paid',
        'amount'     => 18000,
        'currency'   => 'SAR',
        'invoice_id' => 'inv_ours_123',
    ], $overrides);
}

const OURS  = 'inv_ours_123';
const CENTS = 18000;

// ---------------------------------------------------------------
echo "\nIs this payment good for this booking?\n";
// ---------------------------------------------------------------

check('a paid payment for the right amount on our invoice is accepted',
    ['paid' => true, 'reason' => 'ok'],
    moyasar_payment_is_acceptable(payment(), CENTS, OURS));

check('a payment that is not paid is rejected',
    'not_paid',
    moyasar_payment_is_acceptable(payment(['status' => 'failed']), CENTS, OURS)['reason']);

check('an initiated (not yet completed) payment is rejected',
    'not_paid',
    moyasar_payment_is_acceptable(payment(['status' => 'initiated']), CENTS, OURS)['reason']);

check('a payment for too little is rejected',
    'amount_mismatch',
    moyasar_payment_is_acceptable(payment(['amount' => 100]), CENTS, OURS)['reason']);

check('a payment in the wrong currency is rejected',
    'currency_mismatch',
    moyasar_payment_is_acceptable(payment(['currency' => 'USD']), CENTS, OURS)['reason']);

check('an API lookup that failed is rejected',
    'lookup_failed',
    moyasar_payment_is_acceptable(payment(['_ok' => false]), CENTS, OURS)['reason']);

// This is the one the binding exists for. Without it, anyone holding the id of
// any other successful 180.00 SAR payment could put it in the callback URL and
// have their own booking confirmed without paying for it.
check('a real paid payment belonging to SOMEONE ELSE\'S invoice is rejected',
    'wrong_invoice',
    moyasar_payment_is_acceptable(payment(['invoice_id' => 'inv_someone_else_999']), CENTS, OURS)['reason']);

check('...and it is rejected as unpaid, not quietly accepted',
    false,
    moyasar_payment_is_acceptable(payment(['invoice_id' => 'inv_someone_else_999']), CENTS, OURS)['paid']);

// Fail closed: if we cannot prove the payment and the booking belong together,
// we do not confirm, even though every other field looks right.
check('a booking with no invoice recorded cannot be confirmed',
    'unbound_payment',
    moyasar_payment_is_acceptable(payment(), CENTS, '')['reason']);

check('a payment carrying no invoice id cannot be confirmed',
    'unbound_payment',
    moyasar_payment_is_acceptable(payment(['invoice_id' => '']), CENTS, OURS)['reason']);

check('a missing invoice_id field is treated the same as an empty one',
    'unbound_payment',
    moyasar_payment_is_acceptable(
        ['_ok' => true, 'status' => 'paid', 'amount' => CENTS, 'currency' => 'SAR'], CENTS, OURS)['reason']);

check('the amount is compared exactly, not loosely (17999 halalas is not 18000)',
    'amount_mismatch',
    moyasar_payment_is_acceptable(payment(['amount' => 17999]), CENTS, OURS)['reason']);

check('a lowercase currency still matches',
    true,
    moyasar_payment_is_acceptable(payment(['currency' => 'sar']), CENTS, OURS)['paid']);

// ---------------------------------------------------------------
echo "\nSimulate mode still behaves as before\n";
// ---------------------------------------------------------------

check('simulate mode confirms a sim_paid_ id',
    true,  moyasar_verify_payment('sim_paid_7', CENTS)['paid']);
check('simulate mode refuses a sim_failed_ id',
    false, moyasar_verify_payment('sim_failed_7', CENTS)['paid']);
check('simulate mode does not need an invoice to be bound',
    true,  moyasar_verify_payment('sim_paid_7', CENTS, '')['paid']);

// ---------------------------------------------------------------
echo "\nAmounts are converted to halalas correctly\n";
// ---------------------------------------------------------------

check('180.00 SAR is 18000 halalas', 18000, payment_amount_halalas('180.00'));
check('75.50 SAR is 7550 halalas',    7550, payment_amount_halalas('75.50'));
check('a float that cannot be represented exactly still rounds correctly',
    12035, payment_amount_halalas(120.35));

// ---------------------------------------------------------------
echo "\nCallback address\n";
// ---------------------------------------------------------------

// The constant is fixed once the process starts, so each case runs in its own
// php process with its own environment. That also proves the env var is really
// wired through config/payments.php rather than read somewhere by accident.
function base_url_with(?string $envValue, array $server = []): string {
    $code = 'define("MALAEB",1);'
          . '$_SERVER["HTTP_HOST"]=' . var_export($server['HTTP_HOST'] ?? 'localhost', true) . ';'
          . '$_SERVER["SCRIPT_NAME"]=' . var_export($server['SCRIPT_NAME'] ?? '/wagti/pay.php', true) . ';'
          . 'require "' . dirname(__DIR__) . '/config/payments.php";'
          . 'require "' . dirname(__DIR__) . '/includes/payments.php";'
          . 'echo payment_base_url();';
    $env = $envValue === null ? '' : 'WAGTI_BASE_URL=' . escapeshellarg($envValue) . ' ';
    return trim((string) shell_exec($env . 'php -d error_log=/dev/null -r ' . escapeshellarg($code) . ' 2>/dev/null'));
}

check('with no setting, the request host is used (local XAMPP keeps working)',
    'http://localhost/wagti',
    base_url_with(null));

check('a spoofed Host header is what makes that fall-back untrustworthy',
    'http://evil.example.com/wagti',
    base_url_with(null, ['HTTP_HOST' => 'evil.example.com']));

// ...which is exactly what the setting is for:
check('with WAGTI_BASE_URL set, a spoofed Host header is ignored',
    'https://wagti.example.com',
    base_url_with('https://wagti.example.com', ['HTTP_HOST' => 'evil.example.com']));

check('a trailing slash on the setting does not produce a double slash',
    'https://wagti.example.com',
    base_url_with('https://wagti.example.com/', ['HTTP_HOST' => 'evil.example.com']));

check('a subfolder install is preserved',
    'https://example.com/wagti',
    base_url_with('https://example.com/wagti', ['HTTP_HOST' => 'evil.example.com']));

check('a setting that is not a URL is ignored rather than used blindly',
    'http://localhost/wagti',
    base_url_with('not a url'));

check('a non-http scheme is ignored',
    'http://localhost/wagti',
    base_url_with('javascript:alert(1)'));

// ---------------------------------------------------------------
echo "\nProduction never falls back to the simulated checkout\n";
// ---------------------------------------------------------------

// Like the callback-address checks, each case gets its own php process so it
// can have its own environment. It reports PAYMENTS_AVAILABLE, what a forged
// "paid" from the test checkout verifies as, and whether invoice creation is
// refused, plus anything written to the error log (stderr in the CLI).
function payments_with(array $env): array {
    $code = 'define("MALAEB",1);'
          . '$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["SCRIPT_NAME"]="/wagti/pay.php";'
          . 'require "' . dirname(__DIR__) . '/config/payments.php";'
          . 'require "' . dirname(__DIR__) . '/includes/payments.php";'
          . '$r = ["available" => PAYMENTS_AVAILABLE];'
          // Only safe to call when it cannot reach the network: disabled, or simulate.
          . 'if (!PAYMENTS_AVAILABLE || PAYMENTS_MODE !== "live") {'
          . '  $r["forged_paid"] = moyasar_verify_payment("sim_paid_7", 18000, "sim_7")["paid"];'
          . '  $r["invoice_ok"]  = moyasar_create_invoice(18000, "test", "http://x/cb", 7)["ok"];'
          . '}'
          . 'echo "\nJSON:" . json_encode($r);';
    $prefix = '';
    foreach ($env as $k => $v) {
        $prefix .= $k . '=' . escapeshellarg($v) . ' ';
    }
    // env -u clears anything inherited, so the machine running the tests
    // cannot leak its own MALAEB_ENV, keys or base URL into a case.
    $cmd = 'env -u MALAEB_ENV -u WAGTI_PAYMENTS_MODE -u MOYASAR_SECRET_KEY -u MOYASAR_PUBLISHABLE_KEY -u WAGTI_BASE_URL '
         . $prefix . 'php -r ' . escapeshellarg($code) . ' 2>&1';
    $out = (string) shell_exec($cmd);
    $json = substr($out, (int) strrpos($out, 'JSON:') + 5);
    $r = json_decode($json, true) ?: [];
    $r['logged_disabled'] = str_contains($out, 'payments are DISABLED');
    $r['logged_keys']     = str_contains($out, 'Production needs WAGTI_PAYMENTS_MODE');
    $r['logged_base_url'] = str_contains($out, 'Production needs WAGTI_BASE_URL');
    return $r;
}

$local = payments_with([]);
check('on your own machine (no MALAEB_ENV) payments stay available', true, $local['available'] ?? null);
check('...and the simulated checkout still confirms, as before',      true, $local['forged_paid'] ?? null);

// Every production case below except the WAGTI_BASE_URL ones sets a valid https
// base URL, so it can only fail for the reason it names.
const GOOD_BASE = 'https://wagti.example.com';

$prodSim = payments_with(['MALAEB_ENV' => 'production', 'WAGTI_BASE_URL' => GOOD_BASE]);
check('production with no payments mode set: payments are switched off', false, $prodSim['available'] ?? null);
check('...a forged "paid" from the test checkout does NOT confirm',       false, $prodSim['forged_paid'] ?? null);
check('...no invoice can be started either',                             false, $prodSim['invoice_ok'] ?? null);
check('...and the server log says why',                                   true,  $prodSim['logged_disabled']);

$prodPlaceholder = payments_with(['MALAEB_ENV' => 'production', 'WAGTI_PAYMENTS_MODE' => 'live',
                                  'WAGTI_BASE_URL' => GOOD_BASE]);
check('production in live mode with placeholder keys: still switched off', false, $prodPlaceholder['available'] ?? null);
check('...and a forged "paid" still does not confirm',                    false, $prodPlaceholder['forged_paid'] ?? null);

$prodHalfKeys = payments_with(['MALAEB_ENV' => 'production', 'WAGTI_PAYMENTS_MODE' => 'live',
                               'MOYASAR_SECRET_KEY' => 'sk_live_realvalue123',
                               'WAGTI_BASE_URL' => GOOD_BASE]);
check('production with only one real key: still switched off', false, $prodHalfKeys['available'] ?? null);

// ---------------------------------------------------------------
echo "\nProduction also needs WAGTI_BASE_URL to be an https:// address\n";
// ---------------------------------------------------------------

// Everything else is ready: live mode and both real keys.
function prod_live_with_base(?string $base): array {
    $env = ['MALAEB_ENV' => 'production', 'WAGTI_PAYMENTS_MODE' => 'live',
            'MOYASAR_SECRET_KEY' => 'sk_live_realvalue123',
            'MOYASAR_PUBLISHABLE_KEY' => 'pk_live_realvalue123'];
    if ($base !== null) {
        $env['WAGTI_BASE_URL'] = $base;
    }
    return payments_with($env);
}

$noBase = prod_live_with_base(null);
check('live + real keys but no WAGTI_BASE_URL: payments are switched off', false, $noBase['available'] ?? null);
check('...a callback cannot confirm a booking',                           false, $noBase['forged_paid'] ?? null);
check('...no invoice can be started',                                     false, $noBase['invoice_ok'] ?? null);
check('...the log names WAGTI_BASE_URL as what is missing',               true,  $noBase['logged_base_url']);
check('...and does not wrongly blame the keys',                           false, $noBase['logged_keys']);

$httpBase = prod_live_with_base('http://wagti.example.com');
check('an http:// WAGTI_BASE_URL: still switched off',  false, $httpBase['available'] ?? null);
check('...and a callback still cannot confirm',         false, $httpBase['forged_paid'] ?? null);
check('...and the log says why',                        true,  $httpBase['logged_base_url']);

// A one-slash typo starts with https:// but has no host, so the callback would
// fall back to the forgeable Host header, the very thing this check exists to
// stop. (Plain "https://" is no test of this: trailing slashes are trimmed off
// WAGTI_BASE_URL, leaving "https:", which fails a prefix check anyway.)
$noHost = prod_live_with_base('https:///wagti');
check('"https:///wagti" (no host): still switched off', false, $noHost['available'] ?? null);

$prodReady = prod_live_with_base(GOOD_BASE);
check('live + real keys + https:// WAGTI_BASE_URL: payments are on', true,  $prodReady['available'] ?? null);
check('...and nothing is logged as disabled',                        false, $prodReady['logged_disabled']);

$localHttp = payments_with(['WAGTI_BASE_URL' => 'http://localhost/malaeb']);
check('on your own machine an http:// base URL is still fine',       true,  $localHttp['available'] ?? null);

// pay.php needs a database, which this suite deliberately runs without, so its
// message is checked on a real server; this pins the wiring it depends on: the
// guard comes before both the test checkout and invoice creation.
$paySrc   = file_get_contents(__DIR__ . '/../pay.php');
$guardPos = strpos($paySrc, 'if (!PAYMENTS_AVAILABLE)');
check('pay.php checks PAYMENTS_AVAILABLE before showing the test checkout',
    true, $guardPos !== false && $guardPos < (int) strpos($paySrc, "view('pay.html'"));
check('...and before starting an invoice',
    true, $guardPos !== false && $guardPos < (int) strpos($paySrc, 'moyasar_create_invoice('));
check('...and tells the customer payment is temporarily unavailable',
    true, str_contains($paySrc, 'Online payment is temporarily unavailable'));

echo "\n" . str_repeat('-', 46) . "\n";
echo "{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
