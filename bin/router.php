<?php
// Router for PHP's built-in server (composer serve): serve real files, send everything else to index.php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
// The JPEG XL copy of an image for the browsers that accept it, like www/.htaccess does on the server
$jxl = $_SERVER['DOCUMENT_ROOT'] . $path . '.jxl';
if (preg_match('#^/images/.+\.(jpe?g|png|webp)$#i', $path) && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'image/jxl')
    && is_file($jxl)
) {
    header('Content-Type: image/jxl');
    header('Vary: Accept');
    readfile($jxl);
    return true;
}
if ($path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
