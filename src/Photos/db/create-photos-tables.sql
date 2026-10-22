-- The photo gallery's tables (albums, photos, deletion requests) and the soirées' photo password (same as migrations/20261023120000_create_photos_tables.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, before deploying the gallery.
-- Given to the soirée's dancers: it opens the soirée's album
ALTER TABLE `events` ADD `photos_password` varchar(100) DEFAULT NULL;

CREATE TABLE `photo_albums` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL,
    `slug` varchar(120) NOT NULL,
    `collection` varchar(100) NOT NULL,
    `photographer` varchar(100) DEFAULT NULL,
    `event_id` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `photo_albums_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `photos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `album_id` int(11) NOT NULL,
    `section` varchar(100) DEFAULT NULL,
    `filename` varchar(64) NOT NULL,
    `width` int(11) NOT NULL,
    `height` int(11) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `album_id` (`album_id`),
    CONSTRAINT `photos_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `photo_albums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `photo_deletion_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `photo_id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `reason` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `photo_id` (`photo_id`),
    CONSTRAINT `photo_deletion_requests_ibfk_1` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- So that Phinx knows this migration is done. If phpMyAdmin says phinxlog doesn't exist, ignore this one
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`)
VALUES (20261023120000, 'CreatePhotosTables', NOW(), NOW(), 0);
