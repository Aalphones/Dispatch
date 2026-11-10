CREATE TABLE post_reactions (
    post_id INT UNSIGNED NOT NULL PRIMARY KEY,
    reaction ENUM('more', 'less') NOT NULL,
    note VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_post_reactions_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
