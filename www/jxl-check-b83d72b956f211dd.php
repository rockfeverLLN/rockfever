<?php
// Temporary: can this server run the JPEG XL encoder that the deploy uploads to bin/cjxl? Removed right after
header('Content-Type: text/plain');
header('X-Robots-Tag: noindex');
$root = dirname(__DIR__);
$hide = fn (string $text) => str_replace([$root, sys_get_temp_dir()], ['<site>', '<temp>'], $text);
$cjxl = "$root/bin/cjxl";
$image = imagecreatetruecolor(2400, 1600);
for ($i = 0; $i < 400; $i++) {
    imagefilledellipse($image, mt_rand(0, 2400), mt_rand(0, 1600), mt_rand(20, 400), mt_rand(20, 400),
        imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
}
$jpg = "$root/tmp/jxl-check.jpg";
imagejpeg($image, $jpg, 85);
foreach (['--lossless_jpeg=1 --num_threads=0', '--lossless_jpeg=1 --num_threads=1', '--lossless_jpeg=1'] as $options) {
    $output = [];
    $started = microtime(true);
    exec(escapeshellarg($cjxl) . ' ' . escapeshellarg($jpg) . ' ' . escapeshellarg("$jpg.jxl") . " $options --quiet 2>&1", $output, $status);
    $blob = (string) @file_get_contents("$jpg.jxl");
    printf("%s: exit %d, %.2f s, jpg %d KB, jxl %d KB, starts %s %s\n", $options, $status, microtime(true) - $started,
        filesize($jpg) / 1024, strlen($blob) / 1024, bin2hex(substr($blob, 0, 4)), $hide(implode(' | ', array_slice($output, -2))));
    @unlink("$jpg.jxl");
}
@unlink($jpg);
