CREATE TABLE stats_visitor_paths (
    day DATE NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    path VARCHAR(300) NOT NULL,
    PRIMARY KEY (day, visitor_hash, path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
