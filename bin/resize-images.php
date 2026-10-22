<?php
// Makes the smaller WebP copies (www/images/about/sizes/) of the images uploaded before they were made at upload.
// The home page offers them in a srcset so phones don't download 1600px photos. Copies that exist are kept.
//
//   php bin/resize-images.php

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$upload = new App\Main\AboutUpload();
foreach (glob('www/images/about/*') as $image) {
    if (is_file($image)) {
        $upload->generateWidths($image);
        echo $image, "\n";
    }
}
