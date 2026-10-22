-- The photo gallery's tables (same as migrations/20261023120000_create_photos_tables.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, before deploying the gallery.
CREATE TABLE `photos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `filename` varchar(64) NOT NULL,
    `width` int(11) NOT NULL,
    `height` int(11) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Empty until an admin sets it on /admin/photos: until then, only the team sees the photos
INSERT INTO `values` (`name`, `label`, `type`, `value`)
VALUES ('PHOTOS_PASSWORD', 'Mot de passe de la galerie photos', 'password', '');

-- So that Phinx knows this migration is done. If phpMyAdmin says phinxlog doesn't exist, ignore this one
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`)
VALUES (20261023120000, 'CreatePhotosTables', NOW(), NOW(), 0);
