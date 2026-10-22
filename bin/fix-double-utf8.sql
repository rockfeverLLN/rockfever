-- Same repair as bin/fix-double-utf8.php, for phpMyAdmin (the OVH plan has no SSH to run the PHP script):
-- turns text saved double-encoded, e.g. "ModÃ©rateur", back into "Modérateur".
-- A value is only changed when its characters are all latin1 and their bytes form valid UTF-8, so text that is
-- already right ("Modérateur", "🎸") is left alone and running this twice changes nothing.
-- Without strict mode, CONVERT() turns invalid UTF-8 into "?" with a warning instead of stopping the query:
-- that is what the second condition of each WHERE relies on. The warnings can be ignored.
SET SESSION sql_mode = '';

UPDATE `aboutSections` SET `title` = CONVERT(CAST(CONVERT(`title` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`title` USING latin1) USING utf8mb4) AS BINARY) = CAST(`title` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`title` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`title` USING latin1) AS BINARY)
  AND `title` REGEXP '[^ -~]';

UPDATE `aboutSections` SET `content` = CONVERT(CAST(CONVERT(`content` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`content` USING latin1) USING utf8mb4) AS BINARY) = CAST(`content` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`content` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`content` USING latin1) AS BINARY)
  AND `content` REGEXP '[^ -~]';

UPDATE `dancers` SET `firstname` = CONVERT(CAST(CONVERT(`firstname` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`firstname` USING latin1) USING utf8mb4) AS BINARY) = CAST(`firstname` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`firstname` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`firstname` USING latin1) AS BINARY)
  AND `firstname` REGEXP '[^ -~]';

UPDATE `dancers` SET `lastname` = CONVERT(CAST(CONVERT(`lastname` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`lastname` USING latin1) USING utf8mb4) AS BINARY) = CAST(`lastname` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`lastname` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`lastname` USING latin1) AS BINARY)
  AND `lastname` REGEXP '[^ -~]';

UPDATE `events` SET `title` = CONVERT(CAST(CONVERT(`title` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`title` USING latin1) USING utf8mb4) AS BINARY) = CAST(`title` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`title` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`title` USING latin1) AS BINARY)
  AND `title` REGEXP '[^ -~]';

UPDATE `events` SET `description` = CONVERT(CAST(CONVERT(`description` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`description` USING latin1) USING utf8mb4) AS BINARY) = CAST(`description` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`description` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`description` USING latin1) AS BINARY)
  AND `description` REGEXP '[^ -~]';

UPDATE `events` SET `place` = CONVERT(CAST(CONVERT(`place` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`place` USING latin1) USING utf8mb4) AS BINARY) = CAST(`place` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`place` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`place` USING latin1) AS BINARY)
  AND `place` REGEXP '[^ -~]';

UPDATE `events` SET `price` = CONVERT(CAST(CONVERT(`price` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`price` USING latin1) USING utf8mb4) AS BINARY) = CAST(`price` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`price` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`price` USING latin1) AS BINARY)
  AND `price` REGEXP '[^ -~]';

UPDATE `musics` SET `title` = CONVERT(CAST(CONVERT(`title` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`title` USING latin1) USING utf8mb4) AS BINARY) = CAST(`title` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`title` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`title` USING latin1) AS BINARY)
  AND `title` REGEXP '[^ -~]';

UPDATE `musics` SET `artist` = CONVERT(CAST(CONVERT(`artist` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`artist` USING latin1) USING utf8mb4) AS BINARY) = CAST(`artist` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`artist` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`artist` USING latin1) AS BINARY)
  AND `artist` REGEXP '[^ -~]';

UPDATE `roles` SET `label` = CONVERT(CAST(CONVERT(`label` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`label` USING latin1) USING utf8mb4) AS BINARY) = CAST(`label` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`label` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`label` USING latin1) AS BINARY)
  AND `label` REGEXP '[^ -~]';

UPDATE `values` SET `name` = CONVERT(CAST(CONVERT(`name` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`name` USING latin1) USING utf8mb4) AS BINARY) = CAST(`name` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`name` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`name` USING latin1) AS BINARY)
  AND `name` REGEXP '[^ -~]';

UPDATE `values` SET `value` = CONVERT(CAST(CONVERT(`value` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`value` USING latin1) USING utf8mb4) AS BINARY) = CAST(`value` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`value` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`value` USING latin1) AS BINARY)
  AND `value` REGEXP '[^ -~]';

UPDATE `values` SET `label` = CONVERT(CAST(CONVERT(`label` USING latin1) AS BINARY) USING utf8mb4)
WHERE CAST(CONVERT(CONVERT(`label` USING latin1) USING utf8mb4) AS BINARY) = CAST(`label` AS BINARY)
  AND CAST(CONVERT(CAST(CONVERT(`label` USING latin1) AS BINARY) USING utf8mb4) AS BINARY) = CAST(CONVERT(`label` USING latin1) AS BINARY)
  AND `label` REGEXP '[^ -~]';
