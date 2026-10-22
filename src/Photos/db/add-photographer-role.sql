-- The photographers' login (same as migrations/20261024090000_add_photographer_role.php).
-- No SSH on the OVH plan: paste this in phpMyAdmin's SQL tab once, before deploying.
-- Without a password until an admin gives it one in Paramètres > Utilisateurs: nobody can log in with it before.
INSERT INTO `roles` (`role`, `label`, `password`) VALUES ('photographer', 'Photographe', '');
