CREATE DATABASE IF NOT EXISTS `booky` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `booky`;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','lector') NOT NULL DEFAULT 'lector',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS authors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    bio TEXT NULL,
    country VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_id INT UNSIGNED NOT NULL,
    title VARCHAR(220) NOT NULL,
    subtitle VARCHAR(220) NULL,
    description TEXT NULL,
    genre VARCHAR(80) NULL,
    language VARCHAR(40) NULL DEFAULT 'es',
    isbn VARCHAR(32) NULL,
    target_pages INT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'en_proceso',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY books_author (author_id),
    CONSTRAINT books_author_fk FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_parts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    title VARCHAR(220) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY book_parts_book (book_id, sort_order),
    CONSTRAINT book_parts_book_fk FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    title VARCHAR(220) NOT NULL,
    kind ENUM('introduction','chapter','epilogue') NOT NULL DEFAULT 'chapter',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY chapters_book (book_id, sort_order),
    KEY chapters_part (part_id),
    CONSTRAINT chapters_book_fk FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
    CONSTRAINT chapters_part_fk FOREIGN KEY (part_id) REFERENCES book_parts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    chapter_id INT UNSIGNED NULL,
    kind ENUM('outline','synopsis','chapter','pdf') NOT NULL,
    title VARCHAR(180) NOT NULL,
    original_name VARCHAR(220) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    page_count INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY documents_book_kind (book_id, kind),
    KEY documents_chapter (chapter_id),
    CONSTRAINT documents_book_fk FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
    CONSTRAINT documents_chapter_fk FOREIGN KEY (chapter_id) REFERENCES chapters (id) ON DELETE CASCADE,
    CONSTRAINT documents_user_fk FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_books (
    user_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, book_id),
    KEY user_books_book (book_id),
    CONSTRAINT user_books_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT user_books_book_fk FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario inicial: admin@booky.local / changeme
INSERT INTO users (name, email, password_hash, role, is_active) VALUES
('Admin', 'admin@booky.local', '$2y$12$k2ZNgYJqVn7yROK.sNtcNOuPeJ19RU/optdF.0H/MnQDqA5OxC98.', 'admin', 1);
