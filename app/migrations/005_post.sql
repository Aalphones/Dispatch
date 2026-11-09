CREATE TABLE posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(300) NULL,
    kicker VARCHAR(80) NULL,
    excerpt VARCHAR(400) NULL,
    content_html MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'draft',
    tags JSON NOT NULL,
    layout ENUM('standard', 'feature', 'longform', 'briefing') NOT NULL DEFAULT 'standard',
    accent CHAR(7) NULL,
    cover_media_id INT UNSIGNED NULL,
    seo_title VARCHAR(200) NULL,
    seo_description VARCHAR(300) NULL,
    origin ENUM('scheduled', 'queue', 'manual') NOT NULL DEFAULT 'manual',
    queue_item_id INT UNSIGNED NULL,
    depth ENUM('standard', 'deep') NOT NULL DEFAULT 'standard',
    editor_notes MEDIUMTEXT NULL,
    review_note TEXT NULL,
    ai_assisted TINYINT(1) NOT NULL DEFAULT 1,
    word_count INT UNSIGNED NOT NULL DEFAULT 0,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    published_revision INT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_posts_status (status),
    INDEX idx_posts_queue_item (queue_item_id),
    CONSTRAINT fk_posts_cover_media FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL,
    snapshot JSON NOT NULL,
    actor VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_post_revisions (post_id, revision),
    CONSTRAINT fk_post_revisions_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_categories (
    post_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (post_id, category_id),
    CONSTRAINT fk_post_categories_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_categories_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    ref VARCHAR(60) NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    kind ENUM('web', 'news', 'study', 'official', 'dataset', 'book', 'interview', 'video', 'podcast', 'social', 'other') NOT NULL,
    title VARCHAR(500) NOT NULL,
    url VARCHAR(2000) NULL,
    publisher VARCHAR(300) NULL,
    author VARCHAR(300) NULL,
    published VARCHAR(10) NULL,
    accessed DATE NULL,
    note TEXT NULL,
    UNIQUE KEY uq_sources_ref (post_id, ref),
    CONSTRAINT fk_sources_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
