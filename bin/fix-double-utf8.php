<?php
// Repairs text that was saved double-encoded, e.g. "MÃ¥neskin" instead of "Måneskin".
// Before config.php set charset=utf8mb4, PDO used the MySQL server's default charset (latin1 on old setups),
// so the UTF-8 bytes of "å" were read as the two latin1 characters "Ã¥" and saved as such.
// Scans every text column of every table and only touches values that decode back to valid UTF-8.
//
//   php bin/fix-double-utf8.php           # list what would change
//   php bin/fix-double-utf8.php --apply   # write the fixes

$config = require dirname(__DIR__) . '/config/database.php';
$apply = in_array('--apply', $argv, true);

$pdo = new PDO(
    'mysql:host=' . $config['database.host'] . ';dbname=' . $config['database.name'] . ';charset=utf8mb4',
    $config['database.username'],
    $config['database.password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// MySQL's latin1 is Windows-1252, with its 5 unassigned bytes mapped to the C1 controls of the same number
$latin1 = [];
for ($byte = 0x80; $byte <= 0xFF; $byte++) {
    $char = mb_convert_encoding(chr($byte), 'UTF-8', 'Windows-1252');
    $latin1[$char === '?' ? mb_chr($byte) : $char] = chr($byte);
}

function undouble(string $value, array $latin1): ?string
{
    if (!preg_match('/[^\x00-\x7F]/', $value)) {
        return null;
    }
    $bytes = '';
    foreach (mb_str_split($value) as $char) {
        if (strlen($char) === 1) {
            $bytes .= $char;
        } elseif (isset($latin1[$char])) {
            $bytes .= $latin1[$char];
        } else {
            return null;
        }
    }
    return mb_check_encoding($bytes, 'UTF-8') ? $bytes : null;
}

$columns = $pdo->query(
    "SELECT c.TABLE_NAME, c.COLUMN_NAME, k.COLUMN_NAME AS id
     FROM information_schema.COLUMNS c
     JOIN information_schema.KEY_COLUMN_USAGE k ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME
         AND k.CONSTRAINT_NAME = 'PRIMARY'
     WHERE c.TABLE_SCHEMA = DATABASE() AND c.DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')
     ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
foreach ($columns as ['TABLE_NAME' => $table, 'COLUMN_NAME' => $column, 'id' => $id]) {
    $rows = $pdo->query("SELECT `$id`, `$column` FROM `$table` WHERE `$column` REGEXP '[^ -~]'")->fetchAll(PDO::FETCH_NUM);
    $update = $pdo->prepare("UPDATE `$table` SET `$column` = ? WHERE `$id` = ?");
    foreach ($rows as [$rowId, $value]) {
        $fixed = undouble($value, $latin1);
        if ($fixed === null) {
            continue;
        }
        $count++;
        echo "$table.$column #$rowId: " . mb_strimwidth($value, 0, 60, '…') . ' -> ' . mb_strimwidth($fixed, 0, 60, '…') . "\n";
        if ($apply) {
            $update->execute([$fixed, $rowId]);
        }
    }
}

echo $count . ($apply ? ' value(s) fixed' : ' value(s) to fix, run again with --apply to write them') . "\n";
