ALTER TABLE links 
ADD COLUMN IF NOT EXISTS pass_hash VARCHAR(255) NULL AFTER expires_at;

-- 1. Upgrade users table with role and status
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER email,
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Persistent authentication tokens (split-token approach)
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(32) NOT NULL UNIQUE,
    hashed_validator VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_selector (selector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Dedicated API Keys table with prefixes and revocation state
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    key_prefix VARCHAR(12) NOT NULL,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_key_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE links 
ADD COLUMN fallback_url VARCHAR(2048) DEFAULT NULL AFTER destination_url;

CREATE TABLE IF NOT EXISTS utm_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    utm_source VARCHAR(100) NOT NULL,
    utm_medium VARCHAR(100) NOT NULL,
    utm_campaign VARCHAR(100) DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default presets for common channels
INSERT INTO utm_presets (name, utm_source, utm_medium, utm_campaign, is_default) VALUES
('WhatsApp Broadcast', 'whatsapp', 'social', 'chat_share', 1),
('Twitter / X Post', 'twitter', 'social', 'post', 0),
('Email Newsletter', 'newsletter', 'email', 'weekly_digest', 0),
('YouTube Description', 'youtube', 'video', 'video_link', 0);

