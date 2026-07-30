<?php
// ============================================================
//  run_tests.php — checks the parts that can be tested without a database:
//  the template filler and the validation rules.
//
//  Run it from the malaeb folder:   php tests/run_tests.php
//  Every test names the behaviour it protects, so a failure tells you what
//  broke rather than just which line number disagreed.
// ============================================================

define('MALAEB', true);
require_once __DIR__ . '/../includes/validation.php';

// Sessions and headers belong to a real request; the template helpers only need
// these two functions to exist, so stub them and load the file directly.
function isLoggedIn(): bool { return false; }
function isAdmin(): bool { return false; }
require_once __DIR__ . '/../includes/template.php';

$passed = 0;
$failed = 0;

function check(string $what, $expected, $actual): void {
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "  ok   {$what}\n";
    } else {
        $failed++;
        echo "  FAIL {$what}\n";
        echo "         expected: " . var_export($expected, true) . "\n";
        echo "         actual:   " . var_export($actual, true) . "\n";
    }
}

function check_true(string $what, bool $actual): void { check($what, true, $actual); }

// ---------------------------------------------------------------
echo "\nTemplate filling\n";
// ---------------------------------------------------------------

$tmp = sys_get_temp_dir() . '/malaeb_tpl_' . getmypid();
@mkdir($tmp);
$templateDir = __DIR__ . '/../templates';

// A throwaway template so these tests do not depend on the real page layouts.
file_put_contents($templateDir . '/__test.html', 'A={{a}} B={{b}}');

check('values are substituted',
    'A=1 B=2',
    view('__test.html', ['a' => '1', 'b' => '2'])->html);

check('a value is HTML-escaped by default',
    'A=&lt;script&gt;alert(1)&lt;/script&gt; B=x',
    view('__test.html', ['a' => '<script>alert(1)</script>', 'b' => 'x'])->html);

check('quotes are escaped, so a value cannot break out of an attribute',
    'A=&quot; onmouseover=&quot;evil() B=x',
    view('__test.html', ['a' => '" onmouseover="evil()', 'b' => 'x'])->html);

check('raw() opts a value out of escaping',
    'A=<b>bold</b> B=x',
    view('__test.html', ['a' => raw('<b>bold</b>'), 'b' => 'x'])->html);

// The bug this replaced: values were substituted one after another, so a value
// containing "{{b}}" became a placeholder that the next round filled in.
check('a value containing another placeholder is printed, not expanded',
    'A={{b}} B=secret',
    view('__test.html', ['a' => raw('{{b}}'), 'b' => 'secret'])->html);

check('an escaped value containing a placeholder is also safe',
    'A={{b}} B=secret',
    view('__test.html', ['a' => '{{b}}', 'b' => 'secret'])->html);

// A blank nobody filled in used to reach the visitor as literal "{{price}}".
file_put_contents($templateDir . '/__test2.html', 'X={{missing}}');
check('an unfilled blank renders empty rather than showing {{missing}}',
    'X=',
    view('__test2.html', ['other' => 'x'])->html);

$threw = false;
try { view('missing_file.html'); } catch (RuntimeException) { $threw = true; }
check_true('a missing template file raises an error instead of rendering blank', $threw);

$threw = false;
try { view('../config/db.php'); } catch (InvalidArgumentException) { $threw = true; }
check_true('a template name cannot escape the templates folder', $threw);

check('e() escapes single quotes too',
    '&#039;', e("'"));

unlink($templateDir . '/__test.html');
unlink($templateDir . '/__test2.html');

// ---------------------------------------------------------------
echo "\nChoice validation\n";
// ---------------------------------------------------------------

check_true('a sport from the list is accepted', valid_choice('Padel', SPORTS));
check('a sport outside the list is rejected', false, valid_choice('Cricket', SPORTS));
check('an empty sport is rejected',            false, valid_choice('', SPORTS));
check_true('a status key from the list is accepted', valid_choice('maintenance', COURT_STATUSES));
check('the status label is not a valid value',  false, valid_choice('Maintenance', COURT_STATUSES));

// ---------------------------------------------------------------
echo "\nDate and time validation\n";
// ---------------------------------------------------------------

check_true('a normal date is accepted', valid_date('2026-03-15'));
check('a date that is not on the calendar is rejected', false, valid_date('2026-02-30'));
check('a mis-shaped date is rejected',                  false, valid_date('15/03/2026'));
check('an empty date is rejected',                      false, valid_date(''));
check('a nonsense date is rejected',                    false, valid_date('tomorrow'));

check_true('HH:MM is accepted',    valid_time('18:00'));
check_true('HH:MM:SS is accepted', valid_time('18:00:00'));
check('an hour above 23 is rejected',   false, valid_time('25:00'));
check('a minute above 59 is rejected',  false, valid_time('18:60'));
check('a missing leading zero is rejected', false, valid_time('9:00'));

check('18:00 and 18:00:00 normalise to the same value',
    normalize_time('18:00'), normalize_time('18:00:00'));

check_true('a whole hour is on the half-hour grid',  on_half_hour('18:00'));
check_true('a half hour is on the half-hour grid',   on_half_hour('18:30'));
check('18:15 is not on the half-hour grid', false, on_half_hour('18:15'));

check('two hours is measured as 2.0',   2.0, slot_hours('18:00', '20:00'));
check('ninety minutes is measured as 1.5', 1.5, slot_hours('18:00', '19:30'));

// ---------------------------------------------------------------
echo "\nBooking slot rules\n";
// ---------------------------------------------------------------

$today    = (new DateTimeImmutable('today'))->format('Y-m-d');
$tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
$farOff   = (new DateTimeImmutable('today'))->modify('+' . (MAX_BOOKING_DAYS_AHEAD + 1) . ' days')->format('Y-m-d');

check('a valid slot tomorrow is accepted', '', validate_slot($tomorrow, '18:00', '20:00'));
check('a valid slot today is accepted',    '', validate_slot($today,    '18:00', '20:00'));

check_true('end before start is refused',
    str_contains(validate_slot($tomorrow, '20:00', '18:00'), 'after start time'));
check_true('end equal to start is refused',
    str_contains(validate_slot($tomorrow, '18:00', '18:00'), 'after start time'));

// This is the check edit_booking.php was missing: it validated the time order
// but never the date, so a booking could be moved into the past.
check_true('a date in the past is refused',
    str_contains(validate_slot($yesterday, '18:00', '20:00'), 'past'));

check_true('a date too far ahead is refused',
    str_contains(validate_slot($farOff, '18:00', '20:00'), 'days ahead'));
check_true('an over-long booking is refused',
    str_contains(validate_slot($tomorrow, '08:00', '23:00'), 'at most'));
check_true('a quarter-hour start is refused',
    str_contains(validate_slot($tomorrow, '18:15', '19:15'), 'half hour'));
check_true('a malformed date is refused',
    str_contains(validate_slot('not-a-date', '18:00', '20:00'), 'valid date'));
check_true('a malformed time is refused',
    str_contains(validate_slot($tomorrow, '99:99', '20:00'), 'valid start and end'));

// ---------------------------------------------------------------
echo "\nAccount field validation\n";
// ---------------------------------------------------------------

check_true('a normal email is accepted', valid_email('saud@example.com'));
check('a string without an @ is rejected', false, valid_email('not-an-email'));
check('an over-long email is rejected',    false, valid_email(str_repeat('a', 115) . '@example.com'));

check_true('an 05 mobile number is accepted', valid_phone('0551112222'));
check('a number that is too short is rejected', false, valid_phone('055111'));
check('a number not starting 05 is rejected',   false, valid_phone('0651112222'));
check('a number with letters is rejected',      false, valid_phone('05511122aa'));

check_true('a normal name is accepted', valid_name('Saud Alqahtani'));
check('a one-character name is rejected', false, valid_name('S'));
check('an over-long name is rejected',    false, valid_name(str_repeat('a', 101)));
check_true('a 100-character name is accepted', valid_name(str_repeat('a', 100)));

// ---------------------------------------------------------------
echo "\nReading form fields\n";
// ---------------------------------------------------------------

check('a missing field reads as an empty string, with no warning', '', input([], 'nope'));
check('surrounding spaces are trimmed', 'hello', input(['x' => '  hello  '], 'x'));
check('an array value does not leak through', '', input(['x' => ['a']], 'x'));

// ---------------------------------------------------------------
echo "\nStored demo passwords\n";
// ---------------------------------------------------------------

// The README hands these out; if the seeded hashes and the documented passwords
// ever drift apart, nobody can log in to the demo.
$sql = file_get_contents(__DIR__ . '/../database.sql');
preg_match("/'admin\@malaeb\.com',\s*'([^']+)'/", $sql, $m);
check_true('the README admin password matches the seeded hash',
    password_verify('admin123', $m[1] ?? ''));
preg_match("/'saud\@example\.com',\s*'([^']+)'/", $sql, $m);
check_true('the README customer password matches the seeded hash',
    password_verify('pass1234', $m[1] ?? ''));

// ---------------------------------------------------------------
echo "\n" . str_repeat('-', 46) . "\n";
echo "{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
