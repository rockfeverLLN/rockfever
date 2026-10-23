-- Fixes the home page text and lets the database store emojis. Run it once in phpMyAdmin
-- (OVH hosting → Bases de données → Accéder à phpMyAdmin → SQL), after deploying the code that reads [dates].
SET NAMES utf8mb4;

-- 1 and 2. Tables created in utf8 (3 bytes per character, "utf8mb3") turn emojis into "?" when they're saved,
-- while accents and "✨" survive. utf8mb4 stores all of them. Only converts a table that is still in utf8mb3,
-- so running this again changes nothing. The emojis already saved as "?" are lost: retype them in the admin
SET @convert = IF((SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aboutSections' AND COLUMN_NAME = 'content') IN ('utf8', 'utf8mb3'),
    'ALTER TABLE `aboutSections` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE statement FROM @convert;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @convert = IF((SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'description') IN ('utf8', 'utf8mb3'),
    'ALTER TABLE `events` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', 'DO 0');
PREPARE statement FROM @convert;
EXECUTE statement;
DEALLOCATE PREPARE statement;

-- 3. The dates of the soirées: the list is now made from src/Main/config.php in place of [dates]
UPDATE `aboutSections` SET `content` = '<p>Les soir&eacute;es ont lieu pendant les quadrimestres (de septembre &agrave; d&eacute;cembre et de f&eacute;vrier &agrave; mai), le mercredi soir et, sauf exception, au Hall Sainte-Barbe &agrave; Louvain-la-Neuve. Elles sont ouvertes &agrave; toutes les personnes de 18 &agrave; 35&nbsp;ans, sur pr&eacute;sentation de leur carte d&rsquo;identit&eacute;.</p>
<p>Inscris-toi sur le site, d&egrave;s une semaine avant la soir&eacute;e et jusqu&rsquo;&agrave; midi le jour m&ecirc;me, pour payer 3&nbsp;&euro; au lieu de 4&nbsp;&euro; sur place. Chaque soir&eacute;e est aussi annonc&eacute;e par un &eacute;v&eacute;nement Facebook.</p>
<p>[dates]</p>
<p><strong>Besoin d&rsquo;un covoiturage ?</strong> Rejoins le groupe Facebook <a href="https://www.facebook.com/groups/liftpourlerockfever">Lift pour le Rock Fever</a>.</p>'
WHERE `title` = 'Dates de nos soirées' AND `content` NOT LIKE '%[dates]%';

-- 4. Spelling, grammar and punctuation of the other sections. Each REPLACE only matches the old text,
-- so running this file again, or after the text was changed in the admin, changes nothing
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'c''est en 2015 que des &eacute;tudiants d&eacute;cident de fonder l&rsquo;ASBL Rock Fever &agrave; l''adresse des &eacute;tudiants, avec comme ultime but de leur permettre', 'c&rsquo;est en 2015 que des &eacute;tudiants d&eacute;cident de fonder l&rsquo;ASBL Rock Fever &agrave; l&rsquo;intention des &eacute;tudiants, avec pour but ultime de leur permettre') WHERE `title` = 'Qui sommes-nous ?';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'pas moins de 300 personnes!', 'pas moins de 300&nbsp;personnes !') WHERE `title` = 'Qui sommes-nous ?';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'non-alcoolis&eacute;es', 'non alcoolis&eacute;es') WHERE `title` = 'Qui sommes-nous ?';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'Suis nous sur les r&eacute;seaux!&nbsp;', 'Suis-nous sur les r&eacute;seaux !') WHERE `title` = 'Qui sommes-nous ?';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, '<em>instagram</em> -&gt; ', '<em>Instagram</em> : ') WHERE `title` = 'Qui sommes-nous ?';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, '<em>facebook&nbsp;</em>-&gt; ', '<em>Facebook</em> : ') WHERE `title` = 'Qui sommes-nous ?';

UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'sur notre site facebook: <a href="https://www.facebook.com/rockfeverlln/photos_albums">Photos!</a>', 'sur notre page Facebook : <a href="https://www.facebook.com/rockfeverlln/photos_albums">les albums photo</a>.') WHERE `title` = 'Photos';

UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'Et pour certainement encore longtemps Motiv&eacute;', 'Et certainement pour encore longtemps. Motiv&eacute;') WHERE `title` = 'Arthur Allebé';

UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'Si vous me cherchez je suis', 'Si vous me cherchez, je suis') WHERE `title` = 'Brieuc Dallemagne';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'Rien ne me rends plus', 'Rien ne me rend plus') WHERE `title` = 'Brieuc Dallemagne';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'Le moindre p&eacute;pin ? n''h&eacute;sitez', 'Le moindre p&eacute;pin ? N''h&eacute;sitez') WHERE `title` = 'Brieuc Dallemagne';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'demander de l''aide ce sera', 'demander de l''aide, ce sera') WHERE `title` = 'Brieuc Dallemagne';

UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'Toujours friant de', 'Toujours friand de') WHERE `title` = 'Cyril Paque';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'sur le dance floor jusqu''au bout de la nuit et qui sait,', 'sur le dancefloor jusqu''au bout de la nuit et, qui sait,') WHERE `title` = 'Cyril Paque';

UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'impossible de m&rsquo;en d&eacute;tacher ?? Aujourd''hui', 'impossible de m&rsquo;en d&eacute;tacher 💃🕺 Aujourd''hui') WHERE `title` = 'Alexandra Ng';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, '(he oui, je suis votre DJ de la soir&eacute;e ?) &mdash; et les r&eacute;seaux ?? (N''h&eacute;sitez donc pas', '(eh oui, je suis votre DJ de la soir&eacute;e 🎧) &mdash; et des r&eacute;seaux 📸📱 N''h&eacute;sitez donc pas') WHERE `title` = 'Alexandra Ng';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'il y a des chances que vous finissez sur nos r&eacute;seaux ? J&rsquo;adore virevolter sur la piste que ce soit', 'il y a des chances que vous finissiez sur nos r&eacute;seaux 😉 J&rsquo;adore virevolter sur la piste, que ce soit') WHERE `title` = 'Alexandra Ng';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, 'petits secrets en coulisses ? Entre', 'petits secrets en coulisses 🤫 Entre') WHERE `title` = 'Alexandra Ng';
UPDATE `aboutSections` SET `content` = REPLACE(`content`, '(he oui, c&rsquo;est gr&acirc;ce', '(eh oui, c&rsquo;est gr&acirc;ce') WHERE `title` = 'Alexandra Ng';
