CREATE TABLE documents (
    doc_key VARCHAR(40) NOT NULL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    public_slug VARCHAR(80) NULL UNIQUE,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    doc_key VARCHAR(40) NOT NULL,
    revision INT UNSIGNED NOT NULL,
    content MEDIUMTEXT NOT NULL,
    note VARCHAR(500) NULL,
    actor VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_document_revisions (doc_key, revision),
    CONSTRAINT fk_document_revisions_document FOREIGN KEY (doc_key) REFERENCES documents (doc_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
