CREATE TABLE queue_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    brief TEXT NULL,
    links JSON NOT NULL,
    status ENUM('open', 'claimed', 'review', 'done', 'failed', 'cancelled') NOT NULL DEFAULT 'open',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 2,
    depth ENUM('standard', 'deep') NOT NULL DEFAULT 'standard',
    category_id INT UNSIGNED NULL,
    claimed_by VARCHAR(120) NULL,
    claim_expires DATETIME NULL,
    post_id INT UNSIGNED NULL,
    result_note TEXT NULL,
    created_by VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_queue_items_pick (status, priority, created_at),
    CONSTRAINT fk_queue_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_queue_items_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE queue_media (
    queue_item_id INT UNSIGNED NOT NULL,
    media_id INT UNSIGNED NOT NULL,
    note VARCHAR(500) NULL,
    own_material TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (queue_item_id, media_id),
    CONSTRAINT fk_queue_media_item FOREIGN KEY (queue_item_id) REFERENCES queue_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_media_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
