-- The dates of the soirées, now planned in the admin (same as migrations/20261103090000_create_soirees_table.php),
-- with those that were in src/Main/config.php.
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, BEFORE deploying the change that uses it.
CREATE TABLE `soirees` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `date` date NOT NULL,
    `week` varchar(10) DEFAULT NULL,
    `cancelled` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `soirees` (`date`, `week`) VALUES
    ('2026-09-30', 'S3'),
    ('2026-10-14', 'S5'),
    ('2026-11-11', 'S9'),
    ('2026-11-18', 'S10');

-- So that Phinx knows this migration is done. If phpMyAdmin says phinxlog doesn't exist, ignore this one
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`)
VALUES (20261103090000, 'CreateSoireesTable', NOW(), NOW(), 0);
