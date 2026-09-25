<?php
// ============================================================
//  tools/create_user.php — create an admin or court-owner account.
//
//  The real server starts from schema.sql, which has no accounts at all,
//  and the website has no page that can create admins or owners (on purpose).
//  This tool is how they get made. It only runs from the command line:
//
//      php tools/create_user.php admin "Your Name" you@example.com 05XXXXXXXX
//      php tools/create_user.php owner "Khalid Al Nakheel" khalid@club.com 05XXXXXXXX
//
//  It asks for a password (min 12 characters). Press Enter to have a strong
//  one generated instead — it is shown once, so store it somewhere safe.
//  On the server, run it with the same MALAEB_DB_* environment as the site.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('MALAEB', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validation.php';

function fail(string $msg): never {
    fwrite(STDERR, "Error: {$msg}\n");
    exit(1);
}

[$script, $role, $name, $email, $phone] = array_pad($argv, 5, '');
$role  = strtolower(trim($role));
$name  = trim($name);
$email = strtolower(trim($email));
$phone = trim($phone);

if (!in_array($role, ['admin', 'owner'], true)) {
    fail("usage: php tools/create_user.php admin|owner \"Full Name\" email 05XXXXXXXX");
}
if (!valid_name($name))   fail('the name must be 2 to 100 characters.');
if (!valid_email($email)) fail('that email address is not valid.');
if (!valid_phone($phone)) fail('the phone must look like 05XXXXXXXX.');

$chk = $conn->prepare("SELECT 1 FROM users WHERE email = ?");
$chk->bind_param("s", $email);
$chk->execute();
if ($chk->get_result()->num_rows > 0) fail("an account with {$email} already exists.");

fwrite(STDOUT, "Password for {$email} (min 12 characters, Enter = generate one): ");
$pass = trim((string)fgets(STDIN));
$generated = false;
if ($pass === '') {
    $pass = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
    $generated = true;
}
if (strlen($pass) < 12) fail('the password must be at least 12 characters.');

$hash = password_hash($pass, PASSWORD_DEFAULT);
$ins = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
$ins->bind_param("sssss", $name, $email, $hash, $phone, $role);
$ins->execute();

fwrite(STDOUT, "\nCreated {$role}: {$name} <{$email}> (user_id {$ins->insert_id})\n");
if ($generated) {
    fwrite(STDOUT, "Generated password (shown once — save it now): {$pass}\n");
}
