<?php
// login.php — LOGIN (logic). Fills templates/login.html.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header("Location: " . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'courts.php'));
        exit;
    }
    $error = "Incorrect email or password.";
}

$content = view('login.html', ['alerts' => $error ? alert_error($error) : '']);
render_page('Login', $content);
