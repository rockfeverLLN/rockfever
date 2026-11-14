<?php

namespace App\Photos;

use App\Photos\Entity\Album;
use App\Photos\Table\PhotoTable;

/**
 * Each album's photos in one .zip kept on the server (storage/zips/<album id>.zip), for AlbumZipAction to send.
 *
 * Built a batch of photos at a time (step()), never in one go: making a whole album's .zip in one request took
 * longer than OVH lets a request run (160 seconds without output), which cut it off, left its temporary file behind
 * and held one of the hosting's few PHP processes all along. Each step adds the photos missing from the .zip and
 * removes those no longer in the album, so it follows uploads and deletions by itself.
 */
class AlbumZip
{
    /**
     * Photos added to the .zip in one step, in bytes. Saving it rewrites the whole file, so a step also copies what
     * was already in it: this keeps a step well under OVH's limit, even for the last steps of a big album
     */
    const STEP_BYTES = 150 * 1024 * 1024;

    /**
     * @var PhotoTable
     */
    private $photoTable;

    /**
     * @var PhotoUpload
     */
    private $upload;

    /**
     * @var string
     */
    private $folder;

    public function __construct(PhotoTable $photoTable, PhotoUpload $upload, ?string $folder = null)
    {
        $this->photoTable = $photoTable;
        $this->upload = $upload;
        $this->folder = $folder ?? dirname(__DIR__, 2) . '/storage/zips';
    }

    public static function isAvailable() : bool
    {
        return class_exists(\ZipArchive::class);
    }

    public function getPath(Album $album) : string
    {
        return $this->folder . '/' . $album->id . '.zip';
    }

    /**
     * The album's photos that have a file, by their name in the .zip (their file's, unique in storage/photos)
     * @return array<string, string> name => full path
     */
    public function getFiles(Album $album) : array
    {
        $files = [];
        foreach ($this->photoTable->findByAlbum($album->id)->fetchAll() as $photo) {
            $path = $this->upload->getPath() . '/' . $photo->filename;
            if (is_file($path)) {
                $files[$photo->filename] = $path;
            }
        }
        return $files;
    }

    /**
     * How many of the album's photos the .zip holds as they are now, out of how many
     * @return array{int, int} [done, total]
     */
    public function getProgress(Album $album) : array
    {
        $files = $this->getFiles($album);
        $zip = $this->open($album, \ZipArchive::RDONLY);
        if (!$zip) {
            return [0, count($files)];
        }
        $done = 0;
        $extra = false;
        foreach ($this->entries($zip) as $name => $size) {
            if (isset($files[$name]) && filesize($files[$name]) === $size) {
                $done++;
            } else {
                $extra = true;
            }
        }
        $zip->close();
        // A photo gone from the album still counts as work left, until a step takes it out
        return [$extra ? max(0, min($done, count($files) - 1)) : $done, count($files)];
    }

    public function isReady(Album $album) : bool
    {
        [$done, $total] = $this->getProgress($album);
        return $total > 0 && $done === $total;
    }

    /**
     * Brings the .zip a step closer to the album: takes out the photos no longer in it, then adds missing ones,
     * up to STEP_BYTES
     *
     * @return bool false when another request is already working on this album's .zip
     */
    public function step(Album $album) : bool
    {
        if (!is_dir($this->folder)) {
            @mkdir($this->folder, 0755, true);
        }
        $lock = fopen($this->folder . '/' . $album->id . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return false;
        }
        try {
            ignore_user_abort(true);
            set_time_limit(150);
            $this->removeLeftovers($album);
            $files = $this->getFiles($album);
            $zip = $this->open($album, \ZipArchive::CREATE);
            if (!$zip) {
                // Unreadable (cut off while written by an older version): started again
                @unlink($this->getPath($album));
                $zip = $this->open($album, \ZipArchive::CREATE);
            }
            if (!$zip) {
                throw new \RuntimeException('Cannot create ' . $this->getPath($album));
            }
            $kept = [];
            foreach ($this->entries($zip) as $name => $size) {
                if (isset($files[$name]) && filesize($files[$name]) === $size) {
                    $kept[$name] = true;
                } else {
                    $zip->deleteName($name);
                }
            }
            $bytes = 0;
            foreach ($files as $name => $path) {
                if (isset($kept[$name])) {
                    continue;
                }
                $zip->addFile($path, $name, 0, 0, \ZipArchive::FL_OVERWRITE);
                // Photos don't get any smaller: stored as they are, which is much quicker
                $zip->setCompressionName($name, \ZipArchive::CM_STORE);
                $bytes += filesize($path);
                if ($bytes >= self::STEP_BYTES) {
                    break;
                }
            }
            // Written to a temporary file next to it, then renamed: cut off here, the previous .zip stays whole
            $zip->close();
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Removes the album's .zip, when the album is deleted
     */
    public function delete(Album $album) : void
    {
        @unlink($this->getPath($album));
        @unlink($this->folder . '/' . $album->id . '.lock');
        $this->removeLeftovers($album, 0);
    }

    private function open(Album $album, int $flags) : ?\ZipArchive
    {
        $path = $this->getPath($album);
        if ($flags === \ZipArchive::RDONLY && !is_file($path)) {
            return null;
        }
        $zip = new \ZipArchive();
        return $zip->open($path, $flags) === true ? $zip : null;
    }

    /**
     * @return array<string, int> name => size
     */
    private function entries(\ZipArchive $zip) : array
    {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat) {
                $entries[$stat['name']] = $stat['size'];
            }
        }
        return $entries;
    }

    /**
     * The temporary files of steps that were cut off while saving (<id>.zip.<random>)
     */
    private function removeLeftovers(Album $album, int $olderThan = 600) : void
    {
        foreach (glob($this->getPath($album) . '.*') ?: [] as $file) {
            if (filemtime($file) < time() - $olderThan) {
                @unlink($file);
            }
        }
    }
}
