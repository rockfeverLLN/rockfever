<?php
namespace Framework;

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
                mkdir($dirname, 777, true);
            }
            $file->moveTo($targetPath);

            $this->shrink($targetPath);
            $this->generateFormats($targetPath);

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
        }
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
            $manager = new ImageManager(['driver' => 'gd']);
            $image = $manager->make($targetPath);
            if (function_exists('exif_read_data')) {
                $image->orientate();
            }
            $image->resize($this->maxSize, $this->maxSize, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->save($targetPath, 80);
        } catch (\Throwable $e) {
            // Keep the original file if it can't be processed
        }
    }

    private function generateFormats($targetPath)
    {
        foreach ($this->formats as $format => $size) {
            $manager = new ImageManager(['driver' => 'gd']);
            $destination = $this->getPathWithSuffix($targetPath, $format);
            [$width, $height] = $size;
            $manager->make($targetPath)->fit($width, $height)->save($destination);
        }
    }
}
