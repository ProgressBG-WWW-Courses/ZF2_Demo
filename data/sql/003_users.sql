CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL,
    UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data — one user per ACL role (guest -> staff -> manager -> admin)
-- Passwords are: {username}123, hashed with password_hash(..., PASSWORD_BCRYPT)
INSERT IGNORE INTO users (id, username, password_hash, role) VALUES
    (1, 'admin',   '$2y$10$tGYnEZ8oH0tF.k.hctBz8e20J2.kgZgog/JlD4YXYSAfGteM7imTa', 'admin'),
    (2, 'staff',   '$2y$10$q4bou2CWVN9AvOypoRdyX.psZKgLJ5kX4WM6geqkLPZsw6Rb7W/8q', 'staff'),
    (3, 'manager', '$2y$10$0O/XaLiXOUAZXoKChUYoWOll.9UG90TopsSTn0y8IXyZbfx13xNzC', 'manager'),
    (4, 'guest',   '$2y$10$AOybxtCWD6d9HlRZnrJQ4ugkNusvf3ISckC/62J.VSV9apY8xuKNy', 'guest');
