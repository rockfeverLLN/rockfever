<?php
// Temporary: can this server run the JPEG XL encoder that the deploy uploads to bin/cjxl? Removed right after
header('Content-Type: text/plain');
header('X-Robots-Tag: noindex');
$yes = fn ($value) => $value ? 'yes' : 'no';
$root = dirname(__DIR__);
$hide = fn (string $text) => str_replace([$root, sys_get_temp_dir()], ['<site>', '<temp>'], $text);
$cjxl = "$root/bin/cjxl";
echo 'cjxl runs: ', $yes(is_executable($cjxl)), "\n";
echo 'open_basedir set: ', $yes((bool) ini_get('open_basedir')), "\n";
foreach (['temp' => sys_get_temp_dir(), 'site tmp' => "$root/tmp"] as $label => $dir) {
    $png = "$dir/jxl-check.png";
    $image = imagecreatetruecolor(64, 48);
    imagefilledrectangle($image, 0, 0, 31, 47, imagecolorallocate($image, 200, 30, 30));
    $written = @imagepng($image, $png);
    echo "$label: png written: ", $yes($written), "\n";
    $output = [];
    exec(escapeshellarg($cjxl) . ' ' . escapeshellarg($png) . ' ' . escapeshellarg("$png.jxl") . ' -d 1 2>&1', $output, $status);
    echo "$label: exit $status: ", $hide(implode(' | ', array_slice($output, -3))), "\n";
    $blob = (string) @file_get_contents("$png.jxl");
    echo "$label: jxl ", strlen($blob), ' bytes, starts ', bin2hex(substr($blob, 0, 8)), "\n";
    @unlink($png);
    @unlink("$png.jxl");
}
