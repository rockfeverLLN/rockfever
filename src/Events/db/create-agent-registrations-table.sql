-- The sign-ups made through an AI assistant (same as migrations/20261104120000_create_agent_registrations_table.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, BEFORE deploying the change that uses it.
CREATE TABLE `agent_registrations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `reference` char(32) NOT NULL,
    `event_id` int(11) NOT NULL,
    `dancer_id` int(11) DEFAULT NULL,
    `firstname` varchar(255) NOT NULL,
    `lastname` varchar(255) NOT NULL,
    `client` varchar(255) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cancelled_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `reference` (`reference`),
    KEY `event_id` (`event_id`),
    KEY `dancer_id` (`dancer_id`),
    CONSTRAINT `agent_registrations_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `agent_registrations_dancer_id` FOREIGN KEY (`dancer_id`) REFERENCES `dancers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- So that Phinx knows this migration is done. If phpMyAdmin says phinxlog doesn't exist, ignore this one
INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`)
VALUES (20261104120000, 'CreateAgentRegistrationsTable', NOW(), NOW(), 0);
