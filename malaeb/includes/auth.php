<?php
// just some helper functions for sessions/login checks

defined('MALAEB') or exit('Direct access is not allowed.');

if (session_status() === PHP_SESSION_NONE) {
    // Lock the session cookie down BEFORE the session starts, otherwise these
    // settings are ignored. HttpOnly keeps JavaScript (and therefore any XSS
    // that slips through) from reading the cookie; SameSite=Lax stops another
    // site from riding along on the customer's session.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path'     => '/',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

// Start a freshly-identified session for a user who just proved who they are.
// The new session id matters: without it, an attacker who plants a known
// session id in the victim's browser still holds a valid one after they log in.
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

// $base is '' for pages at the site root and '../' for pages inside /admin,
// the same convention render_page() uses. Hardcoding '../index.php' meant this
// redirect only pointed somewhere real from one directory.
function requireLogin(string $base = ''): void {
    if (!isLoggedIn()) {
        redirect($base . 'login.php');
    }
}

function requireAdmin(string $base = ''): void {
    if (!isAdmin()) {
        redirect($base . 'index.php');
    }
}

// One place that sends a redirect, so we never forget the exit() that stops the
// rest of the page from running (and being sent) after a Location header.
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function isPost(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------
//  Flash messages: a one-line note stored in the session, shown on the next
//  page, then thrown away. This is what lets every form finish with a redirect
//  instead of rendering the result directly. Rendering it directly means that
//  refreshing the page re-submits the whole form — the browser even warns
//  about it — which is how one click turns into two bookings.
// ---------------------------------------------------------------

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Returns the queued messages as ready-to-print HTML and clears the queue.
function take_flash(): Html {
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= ($f['type'] === 'success' ? alert_success($f['message']) : alert_error($f['message']))->html;
    }
    unset($_SESSION['flash']);
    return raw($out);
}

// ---------------------------------------------------------------
//  CSRF ("cross-site request forgery") protection
//  Another site can make the customer's browser submit a form to us, and the
//  browser helpfully attaches their session cookie. The defence is a secret
//  token that only our own pages know, checked on every write.
// ---------------------------------------------------------------

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): Html {
    return raw('<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">');
}

// Call this at the top of every POST handler. hash_equals compares in constant
// time so the token cannot be guessed one character at a time.
function csrf_check(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired, or this form was submitted from another site. Please go back and try again.');
    }
}
