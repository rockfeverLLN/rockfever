<?php
// Temporary: can this server run the JPEG XL encoder that the deploy uploads to bin/cjxl? Removed right after
header('Content-Type: text/plain');
header('X-Robots-Tag: noindex');
$yes = fn ($value) => $value ? 'yes' : 'no';
$cjxl = dirname(__DIR__) . '/bin/cjxl';
echo 'machine x86_64: ', $yes(php_uname('m') === 'x86_64'), "\n";
echo 'cjxl uploaded: ', $yes(is_file($cjxl)), "\n";
echo 'cjxl executable: ', $yes(is_executable($cjxl)), "\n";
exec(escapeshellarg($cjxl) . ' --version 2>&1', $out, $status);
echo 'cjxl runs: ', $yes($status === 0), "\n";
$base = tempnam(sys_get_temp_dir(), 'jxl');
$png = "$base.png";
$image = imagecreatetruecolor(64, 48);
imagefilledrectangle($image, 0, 0, 31, 47, imagecolorallocate($image, 200, 30, 30));
imagepng($image, $png);
$started = microtime(true);
exec(escapeshellarg($cjxl) . ' ' . escapeshellarg($png) . ' ' . escapeshellarg("$png.jxl") . ' -d 1 --quiet 2>&1', $out, $status);
$blob = (string) @file_get_contents("$png.jxl");
echo 'cjxl encodes: ', $yes($status === 0 && (str_starts_with($blob, "\xFF\x0A") || str_starts_with($blob, "\0\0\0\x0CJXL "))), "\n";
echo 'encode under 1 s: ', $yes(microtime(true) - $started < 1), "\n";
@unlink($base);
@unlink($png);
@unlink("$png.jxl");
