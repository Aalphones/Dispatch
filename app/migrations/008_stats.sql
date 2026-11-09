CREATE TABLE stats_daily (
    day DATE NOT NULL,
    path VARCHAR(300) NOT NULL,
    post_id INT UNSIGNED NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0,
    reads_half INT UNSIGNED NOT NULL DEFAULT 0,
    reads_full INT UNSIGNED NOT NULL DEFAULT 0,
    engaged_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stats_dimensions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    day DATE NOT NULL,
    path VARCHAR(300) NOT NULL,
    dimension ENUM('referrer', 'utm', 'device', 'outbound', 'search') NOT NULL,
    value VARCHAR(300) NOT NULL,
    count INT UNSIGNED NOT NULL DEFAULT 0,
    extra JSON NULL,
    UNIQUE KEY uq_stats_dimensions (day, path, dimension, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stats_visitors (
    day DATE NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    hits_last_hour SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hour_started DATETIME NOT NULL,
    PRIMARY KEY (day, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
