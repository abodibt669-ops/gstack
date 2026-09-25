<?php
// login.php — LOGIN (logic). Fills templates/login.html.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/throttle.php';

$error = '';
$email = '';

if (isPost()) {
    csrf_check();
    $email = input($_POST, 'email');
    $pass  = $_POST['password'] ?? '';

    if (login_blocked($conn, $email)) {
        // Refused without even looking at the password, so a guesser learns
        // nothing from trying more — not even a correct guess gets through.
        $error = "Too many failed attempts. Please wait " . LOGIN_LOCK_MINUTES . " minutes and try again.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            // The stored hash may have been made with older settings; refresh it
            // now, while we have the plain password in hand.
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $upd->bind_param("si", $newHash, $user['user_id']);
                $upd->execute();
            }

            login_succeeded($conn, $email);
            loginUser($user);

            // Each role lands on its own home.
            $home = ['admin' => 'admin/dashboard.php', 'owner' => 'owner/dashboard.php'];
            redirect($home[$user['role']] ?? 'courts.php');
        }

        login_failed($conn, $email);

        // Deliberately the same message whether or not the email exists, so this
        // page cannot be used to find out who has an account here.
        $error = "Incorrect email or password.";
    }
}

$content = view('login.html', [
    'alerts' => $error ? alert_error($error) : '',
    'email'  => $email,
    'csrf'   => csrf_field(),
]);
render_page('Login', $content);
