<?php

namespace App\Photos;

use Framework\Upload;

/**
 * How many of the site's images have their JPEG XL copy (Upload::saveJxl), for the dashboard's progress bar, and
 * the copies still missing made in the background: photos uploaded before, or whose copy a stopped job didn't make
 */
class JxlProgress
{
    /**
     * Folders of images, with their sizes/ copies: the gallery's photos and the images of the pages
     */
    const FOLDERS = ['storage/photos', 'www/images/about', 'www/images/body'];

    /**
     * @return array{total: int, done: int, percent: int}
     */
    public function status() : array
    {
        $images = $this->images();
        $total = count($images);
        $done = $total - count(array_filter($images, fn ($image) => !$this->isDone($image)));
        return [
            'total' => $total,
            'done' => $done,
            // Never 100 while one is missing
            'percent' => $total === 0 ? 100 : ($done === $total ? 100 : min(99, (int) floor(100 * $done / $total)))
        ];
    }

    /**
     * Makes the missing copies, one after the other, for this many seconds at most. One at a time on the server:
     * a second dashboard open, or the next poll, leaves it to the one already at work
     */
    public function catchUp(int $seconds) : void
    {
        $lock = @fopen($this->root() . '/tmp/jxl-catch-up.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            $end = microtime(true) + $seconds;
            $upload = new Upload();
            foreach ($this->images() as $image) {
                if (microtime(true) > $end) {
                    break;
                }
                if (!$this->isDone($image)) {
                    $upload->saveJxl($image);
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * The JPEG and PNG images of FOLDERS (the formats Upload::saveJxl converts), as full paths
     * @return string[]
     */
    private function images() : array
    {
        $images = [];
        foreach (self::FOLDERS as $folder) {
            foreach (['', '/sizes'] as $sizes) {
                $dir = $this->root() . "/$folder$sizes";
                foreach (is_dir($dir) ? scandir($dir) : [] as $name) {
                    if (preg_match('/\.(jpe?g|png)$/i', $name) && is_file("$dir/$name")) {
                        $images[] = "$dir/$name";
                    }
                }
            }
        }
        return $images;
    }

    private function isDone(string $image) : bool
    {
        return file_exists(Upload::getJxlPath($image)) || file_exists(Upload::getNoJxlPath($image));
    }

    private function root() : string
    {
        return dirname(__DIR__, 2);
    }
}
