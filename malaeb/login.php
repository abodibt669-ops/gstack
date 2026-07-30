<?php
// login.php — LOGIN (logic). Fills templates/login.html.
require_once __DIR__ . '/bootstrap.php';

$error = '';
$email = '';

if (isPost()) {
    csrf_check();
    $email = input($_POST, 'email');
    $pass  = $_POST['password'] ?? '';

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

        loginUser($user);
        redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'courts.php');
    }

    // Deliberately the same message whether or not the email exists, so this
    // page cannot be used to find out who has an account here.
    $error = "Incorrect email or password.";
}

$content = view('login.html', [
    'alerts' => $error ? alert_error($error) : '',
    'email'  => $email,
    'csrf'   => csrf_field(),
]);
render_page('Login', $content);
