<?php
// ============================================================
//  i18n_tests.php — the Arabic / English layer.
//
//  Run it from the malaeb folder:   php tests/i18n_tests.php
//  The important one is coverage: every {{t:...}} in a template and every
//  __('...') / render_page('...') in the code must have an Arabic entry, so a
//  new English sentence can't slip into the Arabic site unnoticed.
// ============================================================

define('MALAEB', true);
require_once __DIR__ . '/../includes/validation.php';
function isLoggedIn(): bool { return false; }
function isAdmin(): bool { return false; }
require_once __DIR__ . '/../includes/template.php';

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
function check_true(string $what, bool $actual): void { check($what, true, $actual); }

$root = dirname(__DIR__);
$ar   = require $root . '/includes/lang/ar.php';

// ---------------------------------------------------------------
echo "\nLanguage choice\n";
// ---------------------------------------------------------------
check('command-line runs are English (tests and tools keep their wording)', 'en', current_lang());
check('English pages are left-to-right', 'ltr', lang_dir());
check('English text passes through unchanged', 'Booking cancelled.', __('Booking cancelled.'));
check(':placeholders are filled in',
    'You can only book up to 90 days ahead.',
    __('You can only book up to :days days ahead.', ['days' => 90]));
check('an unknown sentence falls back to itself instead of breaking',
    'Some brand new sentence', __('Some brand new sentence'));

$_GET = ['court_id' => '3', 'lang' => 'en'];
check('the switcher keeps the other query parameters and flips the language',
    '?court_id=3&lang=ar', lang_switch_url());
$_GET = [];

// ---------------------------------------------------------------
echo "\nTemplates\n";
// ---------------------------------------------------------------
$tpl = $root . '/templates/__i18n_test.html';
file_put_contents($tpl, '<p>{{t:Booking cancelled.}} {{name}}</p>');
$out = view('__i18n_test.html', ['name' => '<b>'])->html;
@unlink($tpl);
check('{{t:...}} is translated and {{name}} still escaped', '<p>Booking cancelled. &lt;b&gt;</p>', $out);

// ---------------------------------------------------------------
echo "\nArabic dictionary\n";
// ---------------------------------------------------------------
$keys = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/templates")) as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.html')) {
        preg_match_all('/\{\{\s*t:([^{}]+?)\s*\}\}/', file_get_contents($f->getPathname()), $m);
        foreach ($m[1] as $k) $keys[trim($k)] = 'templates/' . $f->getFilename();
    }
}
$php = array_merge(glob("$root/*.php"), glob("$root/admin/*.php"), glob("$root/owner/*.php"), glob("$root/includes/*.php"));
foreach ($php as $file) {
    $src = file_get_contents($file);
    preg_match_all('/(?:\b__|render_page)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")/', $src, $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        $k = ($hit[1] ?? '') !== '' ? str_replace("\\'", "'", $hit[1]) : ($hit[2] ?? '');
        if ($k !== '' && $k !== 'Some brand new sentence') $keys[$k] = basename($file);
    }
}
// Values that reach __() from the database or a constant rather than a literal.
foreach (SPORTS as $s) $keys[$s] = 'SPORTS';
foreach (COURT_STATUSES as $label) $keys[$label] = 'COURT_STATUSES';
foreach (['Confirmed', 'Pending', 'Cancelled'] as $s) $keys[$s] = 'booking status';

$missing = array_keys(array_diff_key($keys, $ar));
check('every English sentence used anywhere has an Arabic translation', [], $missing);
check_true('the coverage scan actually found the strings (sanity: > 150)', count($keys) > 150);

$badPlaceholders = [];
foreach ($ar as $en => $arText) {
    preg_match_all('/:[a-z]+/', $en, $a);
    preg_match_all('/:[a-z]+/', $arText, $b);
    sort($a[0]); sort($b[0]);
    if ($a[0] !== $b[0]) $badPlaceholders[] = $en;
}
check('Arabic keeps exactly the same :placeholders as the English', [], $badPlaceholders);

$notArabic = array_keys(array_filter($ar, fn($v) => !preg_match('/\p{Arabic}/u', $v)));
check('every translation is actually written in Arabic', [], $notArabic);

preg_match_all("/^\s*(?:'((?:[^'\\\\]|\\\\.)*)'|\"([^\"]*)\")\s*=>/m", file_get_contents("$root/includes/lang/ar.php"), $m);
// \s also matches a newline, so this catches keys whose value sits on the next line.
$total = count($m[0]);
check('no key is written twice in ar.php (the second would silently win)', count($ar), $total);

// ---------------------------------------------------------------
echo "\n" . str_repeat('-', 46) . "\n";
echo "{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
