<?php
// ============================================================
//  i18n.php — Arabic / English.
//
//  The English sentence IS the key: __('Booking cancelled.') returns the
//  Arabic from includes/lang/ar.php when the visitor reads Arabic, and the
//  English unchanged otherwise. A sentence nobody has translated yet falls
//  back to English instead of breaking the page, and the code stays readable.
//  Templates use the same keys: {{t:Booking cancelled.}}
//
//  Which language a visitor gets:
//    1. ?lang=ar or ?lang=en (the switcher in the nav) — remembered in a cookie
//    2. that cookie, on every later visit
//    3. otherwise their browser's first language: Arabic phones get Arabic
//    4. no browser language at all: Arabic
//  Command-line runs (tests, tools/create_user.php) are always English.
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

const SUPPORTED_LANGS = ['ar', 'en'];
const DEFAULT_LANG    = 'ar';
const LANG_COOKIE     = 'wagti_lang';

function current_lang(): string {
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    if (PHP_SAPI === 'cli') {
        return $lang = 'en';
    }

    // An explicit choice from the switcher. Only a GET can change it, and only
    // to one of the two values we support — never echo a raw parameter back.
    $pick = $_GET['lang'] ?? null;
    if (is_string($pick) && in_array($pick, SUPPORTED_LANGS, true)) {
        if (!headers_sent()) {
            setcookie(LANG_COOKIE, $pick, [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => true,
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
        return $lang = $pick;
    }

    $saved = $_COOKIE[LANG_COOKIE] ?? null;
    if (is_string($saved) && in_array($saved, SUPPORTED_LANGS, true)) {
        return $lang = $saved;
    }

    $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    if ($accept !== '') {
        return $lang = str_starts_with($accept, 'ar') ? 'ar' : 'en';
    }
    return $lang = DEFAULT_LANG;
}

function lang_dir(): string {
    return current_lang() === 'ar' ? 'rtl' : 'ltr';
}

// Translate one English sentence. :name placeholders are filled from $vars
// AFTER translating, so the Arabic can put the number wherever it reads best.
function __(string $text, array $vars = []): string {
    static $ar = null;
    $out = $text;
    if (current_lang() === 'ar') {
        $ar ??= require __DIR__ . '/lang/ar.php';
        if (isset($ar[$text])) {
            $out = $ar[$text];
        } else {
            // Shown in English rather than not at all; logged so it gets added.
            static $reported = [];
            if (!isset($reported[$text])) {
                $reported[$text] = true;
                error_log("i18n: no Arabic for \"{$text}\"");
            }
        }
    }
    if ($vars) {
        $pairs = [];
        foreach ($vars as $k => $v) {
            $pairs[':' . $k] = (string)$v;
        }
        $out = strtr($out, $pairs);
    }
    return $out;
}

// Link to the same page in the other language. Query-only and relative, so it
// keeps the current path and every existing parameter (court_id, filters...).
function lang_switch_url(): string {
    $other = current_lang() === 'ar' ? 'en' : 'ar';
    return '?' . http_build_query(array_merge($_GET, ['lang' => $other]));
}
