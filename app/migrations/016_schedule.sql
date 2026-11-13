ALTER TABLE posts
    MODIFY status ENUM('draft', 'review', 'published', 'archived', 'scheduled') NOT NULL DEFAULT 'draft',
    ADD COLUMN schedule_mode ENUM('slot', 'fixed') NULL AFTER published_at,
    ADD COLUMN scheduled_at DATETIME NULL AFTER schedule_mode,
    ADD COLUMN scheduled_since DATETIME NULL AFTER scheduled_at,
    ADD COLUMN release_slot DATETIME NULL AFTER scheduled_since,
    ADD INDEX idx_posts_release_slot (release_slot);
