-- ============================================================
--  schema.sql — Wagti tables for the REAL server. No demo data.
--  Safe to re-run: it only creates tables that don't exist yet and never
--  drops or overwrites anything.
--  After importing, create your admin from the command line:
--      php tools/create_user.php admin "Your Name" you@example.com 05XXXXXXXX
-- ============================================================
-- No CREATE DATABASE or USE line on purpose: create the database first (or use
-- the one your host gave you), select it, and import this file into it. A
-- dedicated database user usually may not create databases, and a hard-coded
-- name would put the tables somewhere the site is not looking.

CREATE TABLE IF NOT EXISTS users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    phone      VARCHAR(20),
    role       ENUM('admin','owner','customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courts (
    court_id       INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    sport_type     ENUM('Football','Padel','Basketball','Volleyball','Tennis') NOT NULL,
    location       VARCHAR(150) NOT NULL,
    price_per_hour DECIMAL(8,2) NOT NULL,
    status         ENUM('available','maintenance') NOT NULL DEFAULT 'available',
    -- Which owner lists this court. NULL means unclaimed (an admin-created
    -- court, or one whose owner account was removed).
    owner_id       INT NULL,
    is_published   TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sport_price (sport_type, price_per_hour),
    INDEX idx_owner (owner_id),
    -- ON DELETE SET NULL on purpose. CASCADE here would mean removing an owner
    -- account silently deleted their courts, and the bookings hanging off those
    -- courts with them. The court survives, unclaimed, for an admin to reassign.
    CONSTRAINT fk_court_owner FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_court_price CHECK (price_per_hour > 0)
);

CREATE TABLE IF NOT EXISTS bookings (
    booking_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    court_id     INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    total_price  DECIMAL(8,2) NOT NULL,
    status       ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',

    -- Two separate references, because they are two different things and the
    -- payment check needs both to still be readable afterwards:
    --   invoice_ref  the invoice we asked the gateway to create for this
    --                booking, written when the customer is sent off to pay.
    --                The callback compares the returned payment against this
    --                to prove the payment belongs to THIS booking.
    --   payment_id   the payment that actually settled it, written only once
    --                the payment has been verified.
    -- Keeping one column for both would mean confirming a booking overwrote
    -- the very value the check was made against, leaving no way to audit
    -- afterwards which invoice a payment answered.
    invoice_ref  VARCHAR(64) NULL,
    payment_id   VARCHAR(64) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(user_id)  ON DELETE CASCADE,
    FOREIGN KEY (court_id) REFERENCES courts(court_id) ON DELETE CASCADE,

    -- The "is this slot already taken?" check runs on every booking attempt and
    -- filters on exactly these three columns. Without an index MySQL reads the
    -- whole table each time.
    INDEX idx_court_date_status (court_id, booking_date, status),
    -- "My bookings" sorts by date for one user.
    INDEX idx_user_date (user_id, booking_date),

    -- A booking that ends before it starts should not be storable at all, no
    -- matter which page (or which future script) writes it.
    CONSTRAINT chk_times CHECK (end_time > start_time),
    CONSTRAINT chk_price CHECK (total_price >= 0)
);

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
