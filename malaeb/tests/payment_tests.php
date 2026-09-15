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

echo "\n" . str_repeat('-', 46) . "\n";
echo "{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
