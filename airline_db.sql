-- ============================================================
-- IS351 Airline Reservation System — Database Schema
-- Group 17 | Semester 1, 2026
-- ============================================================

CREATE DATABASE IF NOT EXISTS airline_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE airline_db;

-- ------------------------------------------------------------
-- Table: users
-- Stores all system users across all roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    DEFAULT NULL,           -- NULL for Google-only accounts
    role            ENUM('admin','staff','passenger')
                                    NOT NULL DEFAULT 'passenger',
    google_id       VARCHAR(255)    DEFAULT NULL,           -- Google OAuth subject ID
    otp_code        VARCHAR(255)    DEFAULT NULL,           -- bcrypt-hashed OTP
    otp_expires_at  DATETIME        DEFAULT NULL,
    login_attempts  INT             NOT NULL DEFAULT 0,
    locked_until    DATETIME        DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email     (email),
    INDEX idx_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: flights
-- Managed by Admin role
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS flights (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    flight_number    VARCHAR(20)     NOT NULL,
    origin           VARCHAR(100)    NOT NULL,
    destination      VARCHAR(100)    NOT NULL,
    departure_time   DATETIME        NOT NULL,
    arrival_time     DATETIME        NOT NULL,
    seats_available  INT             NOT NULL DEFAULT 0,
    price            DECIMAL(10,2)   NOT NULL,
    status           ENUM('scheduled','departed','cancelled')
                                     NOT NULL DEFAULT 'scheduled',
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_status          (status),
    INDEX idx_departure_time  (departure_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: bookings
-- Created by Passenger, managed by Staff
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,
    flight_id       INT             NOT NULL,
    seat_number     VARCHAR(5)      NOT NULL,
    booking_status  ENUM('pending','confirmed','cancelled')
                                    NOT NULL DEFAULT 'pending',
    booked_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_flight_seat (flight_id, seat_number),    -- prevent duplicate seats
    INDEX idx_user_id   (user_id),
    INDEX idx_flight_id (flight_id),

    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_bookings_flight
        FOREIGN KEY (flight_id) REFERENCES flights(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: audit_logs
-- Records all significant user actions for security auditing
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             DEFAULT NULL,              -- NULL for unauthenticated actions
    action      VARCHAR(255)    NOT NULL,
    ip_address  VARCHAR(45)     NOT NULL DEFAULT 'unknown', -- supports IPv6
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id    (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Sample Data — Test Users
-- Password for all accounts: Test1234!
-- Hash generated with password_hash('Test1234!', PASSWORD_BCRYPT)
-- ------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role) VALUES
('Admin User',     'admin@airline.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXqGYuO6K', 'admin'),
('Staff User',     'staff@airline.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXqGYuO6K', 'staff'),
('Passenger User', 'passenger@airline.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXqGYuO6K', 'passenger');

-- ------------------------------------------------------------
-- Sample Data — Flights
-- ------------------------------------------------------------
INSERT INTO flights (flight_number, origin, destination, departure_time, arrival_time, seats_available, price, status) VALUES
('FJ101', 'Suva',     'Nadi',      '2026-06-10 08:00:00', '2026-06-10 09:00:00', 50, 150.00,  'scheduled'),
('FJ202', 'Nadi',     'Auckland',  '2026-06-11 10:00:00', '2026-06-11 15:00:00', 30, 450.00,  'scheduled'),
('FJ303', 'Suva',     'Sydney',    '2026-06-12 14:00:00', '2026-06-12 22:00:00', 20, 600.00,  'scheduled'),
('FJ404', 'Auckland', 'Nadi',      '2026-06-13 09:00:00', '2026-06-13 13:00:00', 45, 420.00,  'scheduled'),
('FJ505', 'Nadi',     'Singapore', '2026-06-14 22:00:00', '2026-06-15 06:00:00', 15, 850.00,  'scheduled');
