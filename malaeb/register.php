<?php
// register.php — REGISTER (logic). Fills templates/register.html.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $pass  = $_POST['password'];

    if (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();

        if ($chk->get_result()->num_rows > 0) {
            $error = "This email is already registered. Try logging in.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
            $ins->bind_param("ssss", $name, $email, $hash, $phone);
            $ins->execute();

            $_SESSION['user_id']   = $ins->insert_id;
            $_SESSION['full_name'] = $name;
            $_SESSION['role']      = 'customer';
            header("Location: courts.php");
            exit;
        }
    }
}

$content = view('register.html', ['alerts' => $error ? alert_error($error) : '']);
render_page('Register', $content);
