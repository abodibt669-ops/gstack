-- Run once on a database created before login throttling existed.
-- Safe to re-run.
USE malaeb_db;

-- Failed logins, used to slow down password guessing (see includes/throttle.php).
-- Rows older than a day are purged automatically.
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id   INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(120) NOT NULL,
    ip           VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip, attempted_at)
);
