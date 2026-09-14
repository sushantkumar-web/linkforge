-- database/migrations/002_add_link_features.sql
ALTER TABLE links 
ADD COLUMN title VARCHAR(255) NULL AFTER user_id,
ADD COLUMN expires_at DATETIME NULL AFTER clicks,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id)
);

CREATE TABLE IF NOT EXISTS link_tags (
    link_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (link_id, tag_id)
);