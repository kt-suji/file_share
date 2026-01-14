CREATE DATABASE IF NOT EXISTS file_share CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE file_share;

CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    password VARCHAR(255) DEFAULT NULL,
    delete_key VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT
);