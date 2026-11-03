<?php
// Temporary: does the site's own upload code make JPEG XL copies on this server? Removed right after
header('Content-Type: text/plain');
header('X-Robots-Tag: noindex');
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$yes = fn ($value) => $value ? 'yes' : 'no';
$dir = 'tmp/jxl-check';
@mkdir("$dir/sizes", 0755, true);
// A photo-like JPEG, and a PNG
$image = imagecreatetruecolor(2400, 1600);
for ($i = 0; $i < 400; $i++) {
    imagefilledellipse($image, mt_rand(0, 2400), mt_rand(0, 1600), mt_rand(20, 400), mt_rand(20, 400),
        imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
}
imagejpeg($image, "$dir/photo.jpg", 85);
$small = imagecreatetruecolor(400, 300);
imagecopyresampled($small, $image, 0, 0, 0, 0, 400, 300, 2400, 1600);
imagepng($small, "$dir/image.png");
$upload = new App\Photos\PhotoUpload($dir);
$started = microtime(true);
$upload->generateWidths("$dir/photo.jpg");
$upload->generateJxl("$dir/photo.jpg");
$upload->saveJxl("$dir/image.png");
printf("time: %.2f s\n", microtime(true) - $started);
foreach (array_merge(["$dir/photo.jpg", "$dir/image.png"], glob("$dir/sizes/*.jpg") ?: []) as $file) {
    $jxl = "$file.jxl";
    $head = bin2hex((string) @file_get_contents($jxl, false, null, 0, 4));
    printf("%s: %d KB => %s\n", basename($file), filesize($file) / 1024,
        is_file($jxl) ? sprintf('%d KB jxl (%s)', filesize($jxl) / 1024, in_array($head, ['0000000c', 'ff0a'], true) || str_starts_with($head, 'ff0a') ? 'valid' : 'NOT JXL') : 'no copy');
}
$upload->delete('photo.jpg');
@unlink("$dir/image.png");
@unlink("$dir/image.png.jxl");
echo 'left after delete: ', count(glob("$dir/{,sizes/}*", GLOB_BRACE) ?: []) - 1, " files\n";
@rmdir("$dir/sizes");
@rmdir($dir);
// The gallery as it is: counts only
$photos = glob('storage/photos/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
$copies = array_filter($photos, fn ($photo) => is_file("$photo.jxl"));
echo 'gallery photos: ', count($photos), ', with a JPEG XL copy: ', count($copies), "\n";
echo 'leftover .tmp files: ', count(glob('{storage/photos,storage/photos/sizes,www/images/about,www/images/about/sizes}/*.tmp', GLOB_BRACE) ?: []), "\n";
