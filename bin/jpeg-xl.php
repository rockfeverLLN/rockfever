<?php
// Saves a JPEG XL copy next to the images of www/images (photo.jpg => photo.jpg.jxl). www/.htaccess sends it
// instead of the image to the browsers that say they can show JPEG XL. Run it again after adding images: copies
// that exist are kept. Needs cjxl, djxl and ssimulacra2 (brew install jpeg-xl).
//
//   php bin/jpeg-xl.php
//
// - JPEG: repacked without loss, the same picture bit for bit (djxl gives the JPEG back), about 20% smaller
// - PNG: lossless
// - WebP (the sizes/ copies, bg.webp): made again from the image they were made from, at the lowest quality that
//   still looks at least as good as the WebP (ssimulacra2 score), as WebP is already lossy
// A copy that isn't smaller than its image isn't kept: the browser would download more for the same picture.

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

const DISTANCES = [6, 5, 4.5, 4, 3.5, 3, 2.5, 2, 1.5, 1];

function run(string $command, ...$args) : ?string
{
    exec($command . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null', $output, $status);
    return $status === 0 ? implode("\n", $output) : null;
}

/**
 * The image a WebP was made from: sizes/photo-640.webp => photo.jpg, bg.webp => bg.png
 */
function source(string $webp) : ?string
{
    $info = pathinfo($webp);
    $name = $info['filename'];
    $dir = $info['dirname'];
    if (basename($dir) === 'sizes') {
        $name = preg_replace('/-\d+$/', '', $name);
        $dir = dirname($dir);
    }
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $extension) {
        if (is_file("$dir/$name.$extension")) {
            return "$dir/$name.$extension";
        }
    }
    return null;
}

/**
 * The source scaled to the size of the WebP the way Upload::generateWidths does it (GD, turned upright), as a PNG:
 * the picture both copies are compared to
 */
function reference(string $source, string $webp, string $png) : bool
{
    [$width, $height] = getimagesize($webp) ?: [0, 0];
    $image = @imagecreatefromstring(file_get_contents($source));
    if (!$image || !$width) {
        return false;
    }
    imagepalettetotruecolor($image);
    $exif = @exif_read_data($source);
    $orientation = $exif['Orientation'] ?? 1;
    if (in_array($orientation, [2, 4, 5, 7])) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
    }
    $angle = [3 => 180, 4 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90][$orientation] ?? 0;
    $image = $angle ? imagerotate($image, $angle, 0) : $image;
    $copy = imagecreatetruecolor($width, $height);
    imagealphablending($copy, false);
    imagesavealpha($copy, true);
    imagecopyresampled($copy, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
    return imagepng($copy, $png);
}

function score(string $reference, string $image, string $png) : float
{
    if ($image !== $png && run('djxl', $image, $png, '--quiet') === null && run('dwebp', $image, '-o', $png) === null) {
        return 0;
    }
    return (float) run('ssimulacra2', $reference, $png);
}

$tmp = sys_get_temp_dir() . '/jpeg-xl-' . getmypid();
$saved = 0;
$images = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('www/images', FilesystemIterator::SKIP_DOTS));
foreach ($images as $file) {
    $path = $file->getPathname();
    $extension = strtolower($file->getExtension());
    $copy = "$path.jxl";
    // Browsers ask for the favicon on their own, without saying they read JPEG XL there
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp']) || $path === 'www/images/favicon.png' || is_file($copy)) {
        continue;
    }
    if ($extension === 'jpg' || $extension === 'jpeg') {
        run('cjxl', $path, $copy, '--lossless_jpeg=1', '-e', '9', '--quiet');
    } elseif ($extension === 'png') {
        run('cjxl', $path, $copy, '-d', '0', '-e', '9', '--quiet');
    } else {
        $source = source($path);
        if (!$source || !reference($source, $path, "$tmp-reference.png")) {
            echo "skipped $path: no image it was made from\n";
            continue;
        }
        $target = score("$tmp-reference.png", $path, "$tmp-webp.png");
        foreach (DISTANCES as $distance) {
            run('cjxl', "$tmp-reference.png", $copy, '-d', (string) $distance, '-e', '9', '--quiet');
            if (score("$tmp-reference.png", $copy, "$tmp-jxl.png") >= $target || $distance === DISTANCES[count(DISTANCES) - 1]) {
                break;
            }
        }
    }
    if (!is_file($copy) || filesize($copy) >= filesize($path)) {
        @unlink($copy);
        echo "skipped $path: not smaller as JPEG XL\n";
        continue;
    }
    $saved += filesize($path) - filesize($copy);
    printf("%s: %d KB => %d KB\n", $path, filesize($path) / 1024, filesize($copy) / 1024);
}
array_map('unlink', glob("$tmp-*") ?: []);
printf("%d KB saved\n", $saved / 1024);
