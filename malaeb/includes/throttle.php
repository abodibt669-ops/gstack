<?php
// ============================================================
//  includes/throttle.php — slow down password guessing on login.php.
//
//  Every failed login is recorded (email + IP). While an email has
//  LOGIN_MAX_FAILS_EMAIL failures, or an IP has LOGIN_MAX_FAILS_IP failures,
//  inside the last LOGIN_LOCK_MINUTES, further logins for it are refused
//  without even checking the password. A successful login clears the email's
//  record. The per-IP limit is higher because several customers can share one
//  IP (a café, a mobile carrier).
//
//  If the login_attempts table does not exist yet (an older database that
//  hasn't run migrations/001_login_attempts.sql), logins keep working and the
//  problem is written to the error log — a missing table must never lock
//  every customer out.
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

const LOGIN_MAX_FAILS_EMAIL = 5;
const LOGIN_MAX_FAILS_IP    = 20;
const LOGIN_LOCK_MINUTES    = 15;

function throttle_key(string $email): string {
    return strtolower(trim($email));
}

function throttle_ip(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

// True when this email or this IP has failed too often recently.
function login_blocked(mysqli $conn, string $email): bool {
    try {
        $key = throttle_key($email);
        $ip  = throttle_ip();
        $stmt = $conn->prepare(
            "SELECT
                SUM(email = ?) AS by_email,
                SUM(ip = ?)    AS by_ip
             FROM login_attempts
             WHERE attempted_at > NOW() - INTERVAL " . LOGIN_LOCK_MINUTES . " MINUTE
               AND (email = ? OR ip = ?)"
        );
        $stmt->bind_param("ssss", $key, $ip, $key, $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['by_email'] ?? 0) >= LOGIN_MAX_FAILS_EMAIL
            || (int)($row['by_ip'] ?? 0)    >= LOGIN_MAX_FAILS_IP;
    } catch (mysqli_sql_exception $e) {
        error_log('Wagti: login throttling unavailable (' . $e->getMessage() . '). Run migrations/001_login_attempts.sql');
        return false;
    }
}

// Record a failure, and tidy up rows nobody needs any more.
function login_failed(mysqli $conn, string $email): void {
    try {
        $key = throttle_key($email);
        $ip  = throttle_ip();
        $ins = $conn->prepare("INSERT INTO login_attempts (email, ip) VALUES (?, ?)");
        $ins->bind_param("ss", $key, $ip);
        $ins->execute();
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");
    } catch (mysqli_sql_exception $e) {
        error_log('Wagti: could not record failed login (' . $e->getMessage() . ')');
    }
}

// A correct password clears that email's failures.
function login_succeeded(mysqli $conn, string $email): void {
    try {
        $key = throttle_key($email);
        $del = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $del->bind_param("s", $key);
        $del->execute();
    } catch (mysqli_sql_exception $e) {
        error_log('Wagti: could not clear login attempts (' . $e->getMessage() . ')');
    }
}
