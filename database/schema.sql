-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Posts table
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Campaigns table
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body_html TEXT,
    status VARCHAR(50) DEFAULT 'Draft', -- e.g., 'Draft', 'Sent', 'Scheduled', 'Archived'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_at TIMESTAMP NULL DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    total_recipients INT DEFAULT 0,
    successfully_sent INT DEFAULT 0,
    opens_count INT DEFAULT 0,
    clicks_count INT DEFAULT 0,
    bounces_count INT DEFAULT 0
);
