CREATE TABLE IF NOT EXISTS rooms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    number      VARCHAR(10)    NOT NULL,
    type        VARCHAR(50)    NOT NULL,
    price       DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255)   NOT NULL,
    UNIQUE INDEX UNIQ_7CA11A9696901F54 (number),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data (matches the original hardcoded rooms)
INSERT IGNORE INTO rooms (id, number, type, price, description) VALUES
    (1, '101', 'Single', 50.00,  'Cozy single room with garden view'),
    (2, '102', 'Double', 80.00,  'Spacious double room with balcony'),
    (3, '201', 'Suite',  150.00, 'Luxury suite with sea view and jacuzzi'),
    (4, '202', 'Double', 90.00,  'Double room with mountain view'),
    (5, '301', 'Suite',  200.00, 'Presidential suite with private terrace');
