<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use App\Photos\PhotoUpload;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\PhotoTable;
use Framework\Database\NoRecordException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * All the photos of an album in one .zip, as uploaded. Administrators only (PhotoAccess::isAdmin)
 */
class AlbumZipAction
{
    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var PhotoTable
     */
    private $photoTable;

    /**
     * @var PhotoUpload
     */
    private $upload;

    /**
     * @var PhotoAccess
     */
    private $access;

    public function __construct(AlbumTable $albumTable, PhotoTable $photoTable, PhotoUpload $upload, PhotoAccess $access)
    {
        $this->albumTable = $albumTable;
        $this->photoTable = $photoTable;
        $this->upload = $upload;
        $this->access = $access;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        try {
            $album = $this->albumTable->findBySlugForAccess($request->getAttribute('slug'));
        } catch (NoRecordException $e) {
            return new Response(404, ['X-Robots-Tag' => 'noindex'], 'Album introuvable');
        }
        if (!$this->access->isAdmin() || !$this->access->canView($request, $album)) {
            return new Response(403, ['X-Robots-Tag' => 'noindex'], 'Accès refusé');
        }
        if (!class_exists(\ZipArchive::class)) {
            return new Response(501, [], 'Téléchargement indisponible');
        }
        set_time_limit(300);
        $file = tempnam(sys_get_temp_dir(), 'album');
        // Removed once sent
        register_shutdown_function(fn () => @unlink($file));
        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::OVERWRITE);
        $i = 0;
        foreach ($this->photoTable->findByAlbum($album->id)->fetchAll() as $photo) {
            $path = $this->upload->getPath() . '/' . $photo->filename;
            if (is_file($path)) {
                $name = sprintf('%s-%03d.%s', $album->slug, ++$i, pathinfo($path, PATHINFO_EXTENSION));
                $zip->addFile($path, $name);
                // Photos don't get any smaller: store them as they are, it's much quicker
                $zip->setCompressionName($name, \ZipArchive::CM_STORE);
            }
        }
        $zip->close();
        if (!is_file($file) || $i === 0) {
            return new Response(404, ['X-Robots-Tag' => 'noindex'], 'Album vide');
        }
        return new Response(200, [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) filesize($file),
            'Content-Disposition' => 'attachment; filename="rockfever-' . $album->slug . '.zip"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex'
        ], Utils::streamFor(fopen($file, 'rb')));
    }
}
