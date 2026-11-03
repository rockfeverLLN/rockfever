<?php
// Temporary: can this server write JPEG XL? Removed right after it's read
header('Content-Type: text/plain');
header('X-Robots-Tag: noindex');
$yes = fn ($value) => $value ? 'yes' : 'no';
echo 'php ', PHP_MAJOR_VERSION, '.', PHP_MINOR_VERSION, "\n";
echo 'gd jxl: ', $yes(function_exists('imagejxl')), "\n";
$imagick = extension_loaded('imagick');
echo 'imagick: ', $yes($imagick), "\n";
$listed = $imagick && in_array('JXL', \Imagick::queryFormats('JXL'));
echo 'imagick lists jxl: ', $yes($listed), "\n";
$encoded = false;
if ($listed) {
    try {
        $image = new \Imagick();
        $image->newImage(8, 8, 'red');
        $image->setImageFormat('jxl');
        $blob = $image->getImageBlob();
        $encoded = strlen($blob) > 0 && (str_starts_with($blob, "\xFF\x0A") || str_starts_with($blob, "\0\0\0\x0CJXL "));
    } catch (\Throwable $e) {
    }
}
echo 'imagick writes jxl: ', $yes($encoded), "\n";
echo 'vips: ', $yes(extension_loaded('vips')), "\n";
echo 'ffi: ', $yes(extension_loaded('ffi')), "\n";
$exec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))));
echo 'exec: ', $yes($exec), "\n";
$cjxl = false;
if ($exec) {
    @exec('command -v cjxl 2>/dev/null', $out, $status);
    $cjxl = $status === 0 && $out;
}
echo 'cjxl on server: ', $yes($cjxl), "\n";
