<?php

namespace App\Photos;

use Framework\Upload;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Saves the gallery's photos outside the web root (storage/photos), so they're only served by PhotoFileAction,
 * to visitors who gave the password
 */
class PhotoUpload extends Upload
{
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected $path = 'storage/photos';

    protected $maxSize = 2400;

    protected $widths = [480, 960, 1600];

    public function getPath() : string
    {
        return $this->path;
    }

    public function getWidths() : array
    {
        return $this->widths;
    }

    /**
     * Saves the photo and its smaller copies
     * @return array|null [filename, width, height], or null if the file isn't a photo
     */
    public function uploadPhoto(UploadedFileInterface $file) : ?array
    {
        $extension = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if ($file->getError() !== UPLOAD_ERR_OK || !in_array($extension, self::EXTENSIONS)) {
            return null;
        }
        // What the file really is, before anything reads it as a photo
        $size = @getimagesize($file->getStream()->getMetadata('uri'));
        if (!$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
            return null;
        }
        $filename = $this->upload($file);
        if ($filename === null) {
            return null;
        }
        $path = $this->path . '/' . $filename;
        [$width, $height] = getimagesize($path);
        return [$filename, $width, $height];
    }

    /**
     * A random name: the visitor's file name can say who's on the photo, and it can't be guessed
     */
    protected function getFilename(UploadedFileInterface $file) : string
    {
        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        return bin2hex(random_bytes(8)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    }

    /**
     * Phones write where and when a photo was taken in it: saving it again through GD turns it upright and drops
     * that, before the copies are made (a JPEG XL copy keeps what's in the JPEG). Photos larger than $maxSize were
     * already saved again when scaled down
     */
    protected function clean(string $path) : void
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        if (!$exif || !array_intersect_key($exif, ['Orientation' => 1, 'GPSLatitude' => 1, 'Make' => 1, 'DateTimeOriginal' => 1])) {
            return;
        }
        [$width, $height] = getimagesize($path) ?: [0, 0];
        if ($width * $height * 6 > 400 * 1024 * 1024) {
            return;
        }
        ini_set('memory_limit', '512M');
        try {
            (new ImageManager(GdDriver::class))->decodePath($path)->save($path, quality: 85);
        } catch (\Throwable $e) {
            // Keep the photo as it was uploaded
        }
    }
}
