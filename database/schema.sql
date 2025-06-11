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

-- Email Templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    body_html TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Contacts table
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'subscribed', -- e.g., 'subscribed', 'unsubscribed', 'pending'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Application Settings Table
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default Application Settings
INSERT INTO app_settings (setting_key, setting_value) VALUES
('default_from_email', 'noreply@example.com')
ON DUPLICATE KEY UPDATE setting_value = 'noreply@example.com';

INSERT INTO app_settings (setting_key, setting_value) VALUES
('reply_to_email', 'support@example.com')
ON DUPLICATE KEY UPDATE setting_value = 'support@example.com';

INSERT INTO app_settings (setting_key, setting_value) VALUES
('company_name', 'CtpaInstitute.org')
ON DUPLICATE KEY UPDATE setting_value = 'CtpaInstitute.org';

INSERT INTO app_settings (setting_key, setting_value) VALUES
('items_per_page', '20')
ON DUPLICATE KEY UPDATE setting_value = '20';

-- Add any other essential default settings here.
-- For example, a site title or theme setting could be added later.

-- Campaign Recipients Table
-- Stores individual recipients for each campaign send attempt.
CREATE TABLE IF NOT EXISTS campaign_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    contact_id INT DEFAULT NULL, -- Nullable if sending to email not in contacts list, though current plan uses existing contacts
    email_address VARCHAR(255) NOT NULL, -- The email address targeted at the time of send/schedule
    status VARCHAR(50) DEFAULT 'targeted', -- e.g., 'targeted', 'sim_sent', 'sim_failed', 'bounced_simulation'
    processed_at TIMESTAMP NULL DEFAULT NULL, -- Timestamp of when this recipient was processed (e.g., marked as 'sim_sent')
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When this recipient record was created for the campaign

    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL, -- If contact deleted, keep log but nullify link

    INDEX idx_campaign_recipients_campaign_id (campaign_id),
    INDEX idx_campaign_recipients_contact_id (contact_id),
    INDEX idx_campaign_recipients_email_status (email_address, status)
);
