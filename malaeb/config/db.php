<?php
// connects to the database, using the default XAMPP settings

defined('MALAEB') or exit('Direct access is not allowed.');

// Everyone's bookings are stored in local Riyadh time. Without this the server
// decides for itself, and "is this date in the past?" gets a different answer in
// PHP than the customer sees on their own calendar.
date_default_timezone_set('Asia/Riyadh');

// XAMPP defaults, overridable by environment variables so the same code can run
// on a real host without editing (and committing) a password.
$DB_HOST = getenv('MALAEB_DB_HOST') ?: 'localhost';
$DB_USER = getenv('MALAEB_DB_USER') ?: 'root';
$DB_PASS = getenv('MALAEB_DB_PASS') ?: '';   // XAMPP default: empty password
$DB_NAME = getenv('MALAEB_DB_NAME') ?: 'malaeb_db';

// Make mysqli raise exceptions instead of quietly returning false. Without this
// a failed INSERT looks exactly like a successful one, and the customer is told
// their booking is confirmed when nothing was saved.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Log the real reason for us; show the visitor something that does not name
    // the database, the host, or the user account.
    error_log('Malaeb DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Sorry, the site is temporarily unavailable. Please try again shortly.');
}
