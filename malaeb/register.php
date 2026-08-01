<?php
// register.php — REGISTER (logic). Fills templates/register.html.
require_once __DIR__ . '/bootstrap.php';

$error = '';
$name = $email = $phone = '';

if (isPost()) {
    csrf_check();
    $name  = input($_POST, 'full_name');
    $email = input($_POST, 'email');
    $phone = input($_POST, 'phone');
    $pass  = $_POST['password'] ?? '';

    // The form asks the browser to check these too, but the browser is not the
    // one we have to convince — anyone can post to this page directly.
    if (!valid_name($name)) {
        $error = "Please enter your full name (2-100 characters).";
    } elseif (!valid_email($email)) {
        $error = "Please enter a valid email address.";
    } elseif (!valid_phone($phone)) {
        $error = "Please enter a Saudi mobile number in the form 05XXXXXXXX.";
    } elseif (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (strlen($pass) > 200) {
        // bcrypt only reads the first 72 bytes; a huge password is just a way
        // to make the server do pointless work.
        $error = "Password is too long.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO users (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
        $ins->bind_param("ssss", $name, $email, $hash, $phone);

        try {
            // Let the UNIQUE index on email decide, instead of checking first
            // and inserting after. Between those two steps another signup can
            // take the address, and the old code would then hit a raw SQL error.
            $ins->execute();

            loginUser([
                'user_id'   => $ins->insert_id,
                'full_name' => $name,
                'role'      => 'customer',
            ]);
            redirect('courts.php');
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {   // duplicate entry
                $error = "This email is already registered. Try logging in.";
            } else {
                throw $e;
            }
        }
    }
}

$content = view('register.html', [
    'alerts'    => $error ? alert_error($error) : '',
    'full_name' => $name,
    'email'     => $email,
    'phone'     => $phone,
    'csrf'      => csrf_field(),
]);
render_page('Register', $content);
