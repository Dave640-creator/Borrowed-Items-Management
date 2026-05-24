-- ─── Create Database ─────────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS borrow_management CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE borrow_management;

-- ─── Create Table ────────────────────────────────────────────────────────────
-- item_name and borrower_name are capped at 50 characters
-- (mirrors the API-level validation in create.php / update.php)

CREATE TABLE IF NOT EXISTS borrow_records (
    id                   INT           PRIMARY KEY AUTO_INCREMENT,
    item_name            VARCHAR(50)   NOT NULL,
    borrower_name        VARCHAR(50)   NOT NULL,
    borrow_date          DATE          NOT NULL,
    expected_return_date DATE          NOT NULL,
    actual_return_date   DATE          NULL,
    status               ENUM('Borrowed', 'Overdue', 'Returned') DEFAULT 'Borrowed',
    created_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── Sample Data ─────────────────────────────────────────────────────────────
INSERT INTO borrow_records (item_name, borrower_name, borrow_date, expected_return_date, status) VALUES
('HDMI Cable',   'Juan Dela Cruz', '2026-05-20', '2026-05-27', 'Borrowed'),
('USB Charger',  'Maria Santos',   '2026-05-15', '2026-05-22', 'Returned'),
('Microphone',   'Pedro Lopez',    '2026-05-10', '2026-05-17', 'Overdue');
