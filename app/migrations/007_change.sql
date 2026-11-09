CREATE TABLE changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entity ENUM('document', 'media', 'category', 'setting') NOT NULL,
    entity_key VARCHAR(120) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'applied', 'rejected') NOT NULL DEFAULT 'pending',
    created_by VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    decided_by VARCHAR(120) NULL,
    INDEX idx_changes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
