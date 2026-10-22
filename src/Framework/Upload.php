<?php
namespace Framework;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;

class Upload
{

    /**
     * @var string
     */
    protected $path;

    protected $formats = [];

    /**
     * Uploaded images larger than this (in pixels, on either side) are scaled down. null keeps them as is
     * @var int|null
     */
    protected $maxSize = null;

    /**
     * Widths (in pixels) of the smaller WebP copies saved in a sizes/ folder next to each image,
     * so pages can offer them in a srcset (see TextExtension::lazyImages)
     * @var int[]
     */
    protected $widths = [];

    public function __construct(?string $path = null)
    {
        if ($path) {
            $this->path = $path;
        }
    }

    /**
     * @param UploadedFileInterface $file
     * @param null|string $oldFile
     * @return null|string
     */
    public function upload(UploadedFileInterface $file, ?string $oldFile = null) : ?string
    {
        if ($file->getError() === UPLOAD_ERR_OK) {
            $this->delete($oldFile);
            $targetPath = $this->addCopySuffix($this->path . '/' . $file->getClientFilename());
            $dirname = pathinfo($targetPath, PATHINFO_DIRNAME);
            if (!file_exists($dirname)) {
                mkdir($dirname, 0755, true);
            }
            $file->moveTo($targetPath);

            $this->shrink($targetPath);
            $this->generateFormats($targetPath);
            $this->generateWidths($targetPath);

            return pathinfo($targetPath)['basename'];
        }
        return null;
    }

    private function addCopySuffix(string $targetPath) : string
    {
        if (file_exists($targetPath)) {
            return $this->addCopySuffix($this->getPathWithSuffix($targetPath, 'copy'));
        }
        return $targetPath;
    }

    public function delete(?string $oldFile = null) : void
    {
        if ($oldFile) {
            $oldFile = $this->path . '/' . $oldFile;
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
            foreach ($this->formats as $format => $_) {
                $oldFileWithFormat = $this->getPathWithSuffix($oldFile, $format);
                if (file_exists($oldFileWithFormat)) {
                    unlink($oldFileWithFormat);
                }
            }
            foreach ($this->widths as $width) {
                $oldFileWithWidth = self::getResizedPath($oldFile, $width);
                if (file_exists($oldFileWithWidth)) {
                    unlink($oldFileWithWidth);
                }
            }
        }
    }

    /**
     * Path of the WebP copy of an image at the given width: photo.jpg => sizes/photo-640.webp
     */
    public static function getResizedPath(string $path, int $width) : string
    {
        $info = pathinfo($path);
        return $info['dirname'] . '/sizes/' . $info['filename'] . '-' . $width . '.webp';
    }

    /**
     * Saves the WebP copies of an image for the widths smaller than it. Existing copies are kept
     */
    public function generateWidths(string $targetPath) : void
    {
        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if (!$this->widths || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            return;
        }
        [$width, $height] = getimagesize($targetPath) ?: [0, 0];
        // The width can turn out to be the height once the photo is rotated upright
        $missing = array_filter($this->widths, function ($size) use ($targetPath, $width, $height) {
            return $size < max($width, $height) && !file_exists(self::getResizedPath($targetPath, $size));
        });
        if (!$missing || $width * $height * 6 > 400 * 1024 * 1024) {
            return;
        }
        ini_set('memory_limit', '512M');
        // Plain GD, so this doesn't depend on the image library's version
        try {
            $image = @imagecreatefromstring(file_get_contents($targetPath));
            if (!$image) {
                return;
            }
            imagepalettetotruecolor($image);
            $image = $this->rotateUpright($image, $targetPath);
            $width = imagesx($image);
            $missing = array_filter($missing, fn ($size) => $size < $width);
            if (!$missing) {
                return;
            }
            $dirname = pathinfo($targetPath, PATHINFO_DIRNAME) . '/sizes';
            if (!file_exists($dirname)) {
                mkdir($dirname, 0755, true);
            }
            $height = imagesy($image);
            foreach ($missing as $size) {
                $copy = imagecreatetruecolor($size, (int) round($height * $size / $width));
                imagealphablending($copy, false);
                imagesavealpha($copy, true);
                imagecopyresampled($copy, $image, 0, 0, 0, 0, imagesx($copy), imagesy($copy), $width, $height);
                imagewebp($copy, self::getResizedPath($targetPath, $size), 75);
            }
        } catch (\Throwable $e) {
            // Pages fall back to the original image
        }
    }

    /**
     * Applies the EXIF orientation of a photo: GD ignores it, browsers don't
     * @param \GdImage $image
     */
    private function rotateUpright($image, string $path)
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = $exif['Orientation'] ?? 1;
        if (in_array($orientation, [2, 4, 5, 7])) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = [3 => 180, 4 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90][$orientation] ?? 0;
        return $angle ? imagerotate($image, $angle, 0) : $image;
    }

    private function getPathWithSuffix(string $path, string $suffix) : string
    {
        $info = pathinfo($path);
        return $info['dirname'] . '/' . $info['filename'] . '_' . $suffix . '.' . $info['extension'];
    }

    private function shrink(string $targetPath) : void
    {
        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if (!$this->maxSize || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            return;
        }
        [$width, $height] = getimagesize($targetPath) ?: [0, 0];
        if ($width <= $this->maxSize && $height <= $this->maxSize) {
            return;
        }
        // GD holds the whole picture in memory: running out would be a fatal error, not an exception
        ini_set('memory_limit', '512M');
        if ($width * $height * 6 > 400 * 1024 * 1024) {
            return;
        }
        try {
            // Photos are turned upright from their EXIF data when they are read
            (new ImageManager(GdDriver::class))->decodePath($targetPath)
                ->scaleDown($this->maxSize, $this->maxSize)
                ->save($targetPath, quality: 80);
        } catch (\Throwable $e) {
            // Keep the original file if it can't be processed
        }
    }

    private function generateFormats($targetPath)
    {
        $manager = new ImageManager(GdDriver::class);
        foreach ($this->formats as $format => $size) {
            $destination = $this->getPathWithSuffix($targetPath, $format);
            [$width, $height] = $size;
            $manager->decodePath($targetPath)->cover($width, $height)->save($destination);
        }
    }
}
