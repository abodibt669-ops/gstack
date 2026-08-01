<?php
// ============================================================
//  template.php  —  THE LINK BETWEEN PHP AND HTML
//  A .html file holds the page structure with blanks like {{name}}.
//  view() loads that .html file and fills each blank with a value.
//  This is how the PHP logic "connects" to the HTML.
//
//  Two rules make this safe:
//   1. Every value is HTML-escaped automatically. To insert ready-made
//      markup instead, wrap it in raw(...) so you have to say so out loud.
//      Forgetting to escape is the most common way a site gets attacked,
//      so the safe option is the default one.
//   2. All the blanks are filled in ONE pass. A value that happens to
//      contain the text "{{something}}" is printed as-is and can never turn
//      into another blank. (The old version replaced blanks one after
//      another, so a customer registering under the name "{{content}}" saw
//      the whole page body pasted into the navbar.)
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

// A tiny wrapper meaning "this string is already HTML, do not escape it".
final class Html {
    public function __construct(public readonly string $html) {}
    public function __toString(): string { return $this->html; }
}

// Mark a string as ready-made HTML. Only ever call this on markup you built
// yourself — never on something that came from a form or the database.
function raw(string $html): Html {
    return new Html($html);
}

// Escape a value for safe printing inside HTML.
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Load one pure-HTML template and replace its {{placeholders}} in a single pass.
function view(string $file, array $data = []): Html {
    // Template names come from our own code, never from the user, but block
    // traversal anyway so a later refactor cannot turn this into a file-read hole.
    if (preg_match('#(^/|\.\.)#', $file)) {
        throw new InvalidArgumentException("Invalid template name: {$file}");
    }

    $path = __DIR__ . '/../templates/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Template not found: {$file}");
    }
    $html = file_get_contents($path);

    return raw(preg_replace_callback(
        '/\{\{\s*(\w+)\s*\}\}/',
        static function (array $m) use ($data, $file): string {
            $key = $m[1];
            if (!array_key_exists($key, $data)) {
                // An unfilled blank is a bug in the calling .php file. Render
                // nothing rather than printing "{{price}}" at the customer.
                error_log("Template {$file}: no value supplied for {{{$key}}}");
                return '';
            }
            $value = $data[$key];
            return $value instanceof Html ? $value->html : e($value);
        },
        $html
    ));
}

// Put page content inside the shared layout (nav + footer) and print it.
// $base is '' for main pages and '../' for pages inside /admin.
function render_page(string $title, Html $content, string $base = ''): void {
    // the only part of the nav that changes: it depends on who is logged in
    if (isLoggedIn()) {
        $navAuth  = '<li><a href="' . e($base) . 'my_bookings.php">My Bookings</a></li>';
        if (isOwner()) {
            $navAuth .= '<li><a href="' . e($base) . 'owner/dashboard.php">My Courts</a></li>';
        }
        if (isAdmin()) {
            $navAuth .= '<li><a href="' . e($base) . 'admin/dashboard.php">Admin</a></li>';
        }
        $navAuth .= '<li><a class="btn btn-outline btn-sm" href="' . e($base) . 'logout.php">Logout ('
                  . e($_SESSION['full_name'] ?? '') . ')</a></li>';
    } else {
        $navAuth  = '<li><a href="' . e($base) . 'login.php">Login</a></li>'
                  . '<li><a class="btn btn-primary btn-sm" href="' . e($base) . 'register.php">Register</a></li>';
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo view('layout.html', [
        'title'    => $title . ' | Wagti',
        'base'     => $base,
        'nav_auth' => raw($navAuth),
        'content'  => $content,
    ]);
}

// Small helpers to build alert boxes from their HTML templates.
// The Html overload is for the few messages that contain a link we wrote.
function alert_error(string|Html $msg): Html   { return view('partials/alert_error.html',   ['msg' => $msg]); }
function alert_success(string|Html $msg): Html { return view('partials/alert_success.html', ['msg' => $msg]); }

// Render a destructive action as a small POST form instead of a plain link.
// Browsers follow links on their own (an <img src> on another site is enough),
// so anything that changes or deletes data has to travel by POST with a token.
function post_button(string $action, array $fields, string $label, string $class, string $confirm = ''): Html {
    $hidden = csrf_field()->html;
    foreach ($fields as $name => $value) {
        $hidden .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    return view('partials/post_button.html', [
        'action'  => $action,
        'hidden'  => raw($hidden),
        'label'   => $label,
        'class'   => $class,
        'confirm' => $confirm,
    ]);
}
