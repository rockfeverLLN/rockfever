<?php
// Temporary: does work deferred with Framework\Background run after the response is sent here? Removed right after
header('Content-Type: text/plain');
header('X-Robots-Tag: noindex');
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$marker = 'tmp/bg-check.txt';
if (($_GET['step'] ?? '') === 'read') {
    echo 'background job finished: ', is_file($marker) ? 'yes, ' . file_get_contents($marker) : 'no', "\n";
    @unlink($marker);
    return;
}
@unlink($marker);
$started = microtime(true);
Framework\Background::defer(function () use ($marker, $started) {
    sleep(3);
    file_put_contents($marker, sprintf('%.1f s after the request started', microtime(true) - $started));
});
echo 'sapi: ', PHP_SAPI, "\n";
echo 'fastcgi_finish_request: ', function_exists('fastcgi_finish_request') ? 'yes' : 'no', "\n";
