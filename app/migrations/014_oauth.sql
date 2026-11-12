CREATE TABLE oauth_clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(500) NOT NULL UNIQUE,
    kind ENUM('cimd', 'manual') NOT NULL,
    name VARCHAR(120) NOT NULL,
    secret_hash CHAR(64) NULL,
    redirect_uris JSON NOT NULL,
    metadata JSON NULL,
    fetched_at DATETIME NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id CHAR(32) NOT NULL UNIQUE,
    code_hash CHAR(64) NULL UNIQUE,
    client_id VARCHAR(500) NOT NULL,
    redirect_uri VARCHAR(500) NOT NULL,
    challenge VARCHAR(128) NOT NULL,
    scope VARCHAR(200) NOT NULL,
    resource VARCHAR(300) NULL,
    state VARCHAR(500) NULL,
    status ENUM('pending', 'approved', 'denied', 'used') NOT NULL DEFAULT 'pending',
    family CHAR(32) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_oauth_codes_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
