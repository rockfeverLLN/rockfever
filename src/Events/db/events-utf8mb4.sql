-- Emojis in the events' text (a Facebook post's 🎉 🏴‍☠️…): the live table was created in 3-byte utf8, which turns
-- them into "?". No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once. Safe to run again.
ALTER TABLE `events` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
