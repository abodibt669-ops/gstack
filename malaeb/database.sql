-- Malaeb database - SWE 322 project
CREATE DATABASE IF NOT EXISTS malaeb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE malaeb_db;

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
('System Admin',      'admin@malaeb.com',  '$2b$10$N4vEfaTAHpLfBK2ZX/ToTO4DrInfFi2A631i13jEIX0bUWGBcPqFO', '0500000000', 'admin'),
('Saud Alqahtani',    'saud@example.com',  '$2b$10$4eLQL8uLJUmuw3vApobAreqBOxYFbuxz4SsOzuU2W6Bc2OaswZeky', '0551112222', 'customer'),
('Mohammed Alharbi',  'mohd@example.com',  '$2b$10$4eLQL8uLJUmuw3vApobAreqBOxYFbuxz4SsOzuU2W6Bc2OaswZeky', '0553334444', 'customer'),
('Fahad Alotaibi',    'fahad@example.com', '$2b$10$4eLQL8uLJUmuw3vApobAreqBOxYFbuxz4SsOzuU2W6Bc2OaswZeky', '0555556666', 'customer'),
('Nawaf Aldossari',   'nawaf@example.com', '$2b$10$4eLQL8uLJUmuw3vApobAreqBOxYFbuxz4SsOzuU2W6Bc2OaswZeky', '0557778888', 'customer'),
('Yousef Alshehri',   'yousef@example.com','$2b$10$4eLQL8uLJUmuw3vApobAreqBOxYFbuxz4SsOzuU2W6Bc2OaswZeky', '0559990000', 'customer');

CREATE TABLE courts (
    court_id       INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    sport_type     ENUM('Football','Padel','Basketball','Volleyball','Tennis') NOT NULL,
    location       VARCHAR(150) NOT NULL,
    price_per_hour DECIMAL(8,2) NOT NULL,
    status         ENUM('available','maintenance') NOT NULL DEFAULT 'available',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    status       ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(user_id)  ON DELETE CASCADE,
    FOREIGN KEY (court_id) REFERENCES courts(court_id) ON DELETE CASCADE
);

INSERT INTO bookings (user_id, court_id, booking_date, start_time, end_time, total_price, status) VALUES
(2, 1, '2025-11-20', '18:00:00', '20:00:00', 360.00, 'confirmed'),
(3, 2, '2025-11-21', '19:00:00', '20:00:00', 120.00, 'confirmed'),
(4, 4, '2025-11-22', '17:00:00', '19:00:00', 180.00, 'confirmed'),
(5, 5, '2025-11-23', '16:00:00', '17:00:00',  75.00, 'confirmed'),
(6, 3, '2025-11-24', '20:00:00', '22:00:00', 240.00, 'confirmed'),
(2, 2, '2025-11-25', '18:00:00', '19:00:00', 120.00, 'cancelled');
