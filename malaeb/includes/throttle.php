<?php
// ============================================================
//  includes/throttle.php — slow down password guessing on login.php.
//
//  Every failed login is recorded (email + IP). Inside the last
//  LOGIN_LOCK_MINUTES, further logins are refused without even checking the
//  password when any of these is reached:
//
//    LOGIN_MAX_FAILS_PAIR   failures for this email FROM THIS IP. The lock is
//                           on the pair, not the email, so someone guessing a
//                           customer's password on their own device cannot
//                           lock the customer out on theirs.
//    LOGIN_MAX_FAILS_IP     failures from this IP for any email: one device
//                           working through a list of accounts. Higher than
//                           the pair limit because customers can share an IP
//                           (a café, a mobile carrier).
//    LOGIN_MAX_FAILS_EMAIL  failures for this email from ALL IPs together:
//                           guessing spread over many addresses to dodge the
//                           other two. Set far above normal use so that it
//                           only ever trips under a real attack.
//
//  A successful login clears that email's record.
//
//  If the login_attempts table does not exist yet (an older database that
//  hasn't run migrations/001_login_attempts.sql), logins keep working and the
//  problem is written to the error log — a missing table must never lock
//  every customer out.
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

const LOGIN_MAX_FAILS_PAIR  = 5;
const LOGIN_MAX_FAILS_IP    = 20;
const LOGIN_MAX_FAILS_EMAIL = 50;
const LOGIN_LOCK_MINUTES    = 15;

function throttle_key(string $email): string {
    return strtolower(trim($email));
}

function throttle_ip(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

// True when this email from this IP, this IP, or this email everywhere has
// failed too often recently. One query counts all three.
function login_blocked(mysqli $conn, string $email): bool {
    try {
        $key = throttle_key($email);
        $ip  = throttle_ip();
        $stmt = $conn->prepare(
            "SELECT
                SUM(email = ? AND ip = ?) AS by_pair,
                SUM(ip = ?)               AS by_ip,
                SUM(email = ?)            AS by_email
             FROM login_attempts
             WHERE attempted_at > NOW() - INTERVAL " . LOGIN_LOCK_MINUTES . " MINUTE
               AND (email = ? OR ip = ?)"
        );
        $stmt->bind_param("ssssss", $key, $ip, $ip, $key, $key, $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['by_pair'] ?? 0)  >= LOGIN_MAX_FAILS_PAIR
            || (int)($row['by_ip'] ?? 0)    >= LOGIN_MAX_FAILS_IP
            || (int)($row['by_email'] ?? 0) >= LOGIN_MAX_FAILS_EMAIL;
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
