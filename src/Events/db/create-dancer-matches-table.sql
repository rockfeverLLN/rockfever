-- Which names are one dancer in the statistics (same as migrations/20261105090000_create_dancer_matches_table.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, BEFORE deploying the change that uses it.
CREATE TABLE `dancer_matches` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name_key` varchar(520) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `match_key` varchar(520) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `same` tinyint(1) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- So that Phinx knows this migration is done. If phpMyAdmin says phinxlog doesn't exist, ignore this one
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`)
VALUES (20261105090000, 'CreateDancerMatchesTable', NOW(), NOW(), 0);
