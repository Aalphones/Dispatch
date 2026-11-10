CREATE TABLE tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    kind ENUM('mcp', 'oauth_access', 'oauth_refresh', 'owner_refresh') NOT NULL,
    name VARCHAR(120) NOT NULL,
    client_id VARCHAR(500) NULL,
    scope VARCHAR(200) NOT NULL,
    family CHAR(32) NULL,
    expires_at DATETIME NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_tokens_kind_revoked (kind, revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
