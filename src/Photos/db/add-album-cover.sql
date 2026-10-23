-- The cover photo chosen for each album (same as migrations/20261025090000_add_album_cover.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, BEFORE deploying the change that uses it.
ALTER TABLE `photo_albums` ADD `cover_photo_id` int DEFAULT NULL AFTER `event_id`;
