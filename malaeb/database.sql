-- Wagti database
-- The schema keeps the malaeb_db name and the MALAEB_* environment variables:
-- those are internal identifiers, and renaming them would mean every existing
-- install had to re-import and reconfigure for a change nobody can see.
CREATE DATABASE IF NOT EXISTS malaeb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE malaeb_db;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS courts;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    phone      VARCHAR(20),
    role       ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (full_name, email, password, phone, role) VALUES
('System Admin',      'admin@wagti.com',  '$2y$12$UFJ6qMhtEZeDBq.jIzIgjuVxmJkpukIilMHLplI.0IDcjLxoLv0nm', '0500000000', 'admin'),
('Saud Alqahtani',    'saud@example.com',  '$2y$12$LrYa8W.U3ybulhTu01CcLOe9bPtAwvEs0KP5g/VuoM049H9fdf2Y.', '0551112222', 'customer'),
('Mohammed Alharbi',  'mohd@example.com',  '$2y$12$LrYa8W.U3ybulhTu01CcLOe9bPtAwvEs0KP5g/VuoM049H9fdf2Y.', '0553334444', 'customer'),
('Fahad Alotaibi',    'fahad@example.com', '$2y$12$LrYa8W.U3ybulhTu01CcLOe9bPtAwvEs0KP5g/VuoM049H9fdf2Y.', '0555556666', 'customer'),
('Nawaf Aldossari',   'nawaf@example.com', '$2y$12$LrYa8W.U3ybulhTu01CcLOe9bPtAwvEs0KP5g/VuoM049H9fdf2Y.', '0557778888', 'customer'),
('Yousef Alshehri',   'yousef@example.com','$2y$12$LrYa8W.U3ybulhTu01CcLOe9bPtAwvEs0KP5g/VuoM049H9fdf2Y.', '0559990000', 'customer');

CREATE TABLE courts (
    court_id       INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    sport_type     ENUM('Football','Padel','Basketball','Volleyball','Tennis') NOT NULL,
    location       VARCHAR(150) NOT NULL,
    price_per_hour DECIMAL(8,2) NOT NULL,
    status         ENUM('available','maintenance') NOT NULL DEFAULT 'available',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sport_price (sport_type, price_per_hour),
    CONSTRAINT chk_court_price CHECK (price_per_hour > 0)
);

INSERT INTO courts (name, sport_type, location, price_per_hour, status) VALUES
('Al Nakheel Football Field',  'Football',   'Al Nakheel District, Riyadh',  180.00, 'available'),
('Padel Pro Court 1',          'Padel',      'Al Olaya District, Riyadh',    120.00, 'available'),
('Padel Pro Court 2',          'Padel',      'Al Olaya District, Riyadh',    120.00, 'available'),
('Slam Basketball Court',      'Basketball', 'Al Malqa District, Riyadh',     90.00, 'available'),
('Beach Volleyball Arena',     'Volleyball', 'Al Yasmin District, Riyadh',    75.00, 'available'),
('Center Court Tennis',        'Tennis',     'Diplomatic Quarter, Riyadh',   100.00, 'maintenance');

CREATE TABLE bookings (
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

-- Dates are relative to the day this file is imported, so the sample data is
-- always current: a few upcoming bookings you can edit or cancel, plus one in
-- the past and one already cancelled.
INSERT INTO bookings (user_id, court_id, booking_date, start_time, end_time, total_price, status) VALUES
(2, 1, CURDATE() + INTERVAL 1 DAY,  '18:00:00', '20:00:00', 360.00, 'confirmed'),
(3, 2, CURDATE() + INTERVAL 2 DAY,  '19:00:00', '20:00:00', 120.00, 'confirmed'),
(4, 4, CURDATE() + INTERVAL 3 DAY,  '17:00:00', '19:00:00', 180.00, 'confirmed'),
(5, 5, CURDATE() + INTERVAL 4 DAY,  '16:00:00', '17:00:00',  75.00, 'confirmed'),
(6, 3, CURDATE() + INTERVAL 5 DAY,  '20:00:00', '22:00:00', 240.00, 'confirmed'),
(2, 3, CURDATE() + INTERVAL 6 DAY,  '20:00:00', '22:00:00', 240.00, 'confirmed'),
(2, 1, CURDATE() - INTERVAL 7 DAY,  '18:00:00', '19:00:00', 180.00, 'confirmed'),
(2, 2, CURDATE() + INTERVAL 2 DAY,  '18:00:00', '19:00:00', 120.00, 'cancelled');
