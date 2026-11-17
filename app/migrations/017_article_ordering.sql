ALTER TABLE queue_items
    ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER priority;

UPDATE queue_items q
JOIN (
    SELECT id, ROW_NUMBER() OVER (ORDER BY priority, created_at, id) AS position
    FROM queue_items
) ordered ON ordered.id = q.id
SET q.sort_order = ordered.position;

ALTER TABLE posts
    ADD COLUMN schedule_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER scheduled_since;

UPDATE posts p
JOIN (
    SELECT id, ROW_NUMBER() OVER (ORDER BY scheduled_since, id) AS position
    FROM posts
    WHERE status = 'scheduled' AND schedule_mode = 'slot'
) ordered ON ordered.id = p.id
SET p.schedule_order = ordered.position;
