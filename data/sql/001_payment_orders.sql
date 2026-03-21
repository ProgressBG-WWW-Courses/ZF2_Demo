CREATE TABLE IF NOT EXISTS payment_orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     VARCHAR(255)   NOT NULL UNIQUE,
    room_id      INT            NOT NULL,
    amount       DECIMAL(10, 2) NOT NULL,
    currency     VARCHAR(3)     NOT NULL DEFAULT 'GBP',
    state        VARCHAR(20)    NOT NULL DEFAULT 'PENDING',
    checkout_url TEXT,
    created_at   DATETIME       NOT NULL,
    updated_at   DATETIME       NOT NULL,
    INDEX idx_room_id (room_id),
    INDEX idx_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
