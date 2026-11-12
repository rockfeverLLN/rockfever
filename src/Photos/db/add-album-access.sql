-- Each album's own password (same as migrations/20261112090000_add_album_access.php). Empty: the album is public.
-- Albums take their soirée's photo password, which opened them until now, so none opens up by itself.
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, BEFORE deploying the change that uses it.
ALTER TABLE `photo_albums` ADD `password` varchar(100) DEFAULT NULL AFTER `event_id`;
UPDATE `photo_albums` a JOIN `events` e ON e.id = a.event_id SET a.password = e.photos_password
    WHERE e.photos_password <> '';
