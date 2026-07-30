<?php
// ============================================================
//  template.php  —  THE LINK BETWEEN PHP AND HTML
//  A .html file holds the page structure with blanks like {{name}}.
//  view() loads that .html file and fills each blank with a value.
//  This is how the PHP logic "connects" to the HTML.
// ============================================================

// Load one pure-HTML template and replace its {{placeholders}}.
function view(string $file, array $data = []): string {
    $html = file_get_contents(__DIR__ . '/../templates/' . $file);
    foreach ($data as $key => $value) {
        $html = str_replace('{{' . $key . '}}', $value, $html);
    }
    return $html;
}

// Put page content inside the shared layout (nav + footer) and print it.
// $base is '' for main pages and '../' for pages inside /admin.
function render_page(string $title, string $content, string $base = ''): void {
    // the only part of the nav that changes: it depends on who is logged in
    if (isLoggedIn()) {
        $navAuth  = '<li><a href="' . $base . 'my_bookings.php">My Bookings</a></li>';
        if (isAdmin()) {
            $navAuth .= '<li><a href="' . $base . 'admin/dashboard.php">Admin</a></li>';
        }
        $navAuth .= '<li><a class="btn btn-outline btn-sm" href="' . $base . 'logout.php">Logout ('
                  . htmlspecialchars($_SESSION['full_name']) . ')</a></li>';
    } else {
        $navAuth  = '<li><a href="' . $base . 'login.php">Login</a></li>'
                  . '<li><a class="btn btn-primary btn-sm" href="' . $base . 'register.php">Register</a></li>';
    }

    echo view('layout.html', [
        'title'    => htmlspecialchars($title) . ' | Malaeb',
        'base'     => $base,
        'nav_auth' => $navAuth,
        'content'  => $content,
    ]);
}

// Small helpers to build alert boxes from their HTML templates.
function alert_error(string $msg): string   { return view('partials/alert_error.html',   ['msg' => $msg]); }
function alert_success(string $msg): string { return view('partials/alert_success.html', ['msg' => $msg]); }
