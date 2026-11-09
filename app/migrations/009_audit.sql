CREATE TABLE audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    at DATETIME NOT NULL,
    actor VARCHAR(120) NOT NULL,
    action VARCHAR(60) NOT NULL,
    target VARCHAR(160) NOT NULL,
    details JSON NULL,
    INDEX idx_audit_log_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
