-- What each photo shows, for screen readers (same as migrations/20261028090000_add_photo_description.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, BEFORE deploying the change that uses it.
ALTER TABLE `photos` ADD `description` varchar(300) DEFAULT NULL AFTER `section`;
