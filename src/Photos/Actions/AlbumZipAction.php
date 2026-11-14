<?php

namespace App\Photos\Actions;

use App\Photos\AlbumZip;
use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use Framework\Database\NoRecordException;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * All the photos of an album in one .zip, as uploaded. Administrators only (PhotoAccess::isAdmin)
 *
 * Sends the .zip kept on the server (AlbumZip) when it's up to date with the album. Otherwise brings it a step
 * closer and answers a page that shows how far it got and reloads itself, until the .zip is sent
 */
class AlbumZipAction
{
    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var AlbumZip
     */
    private $zip;

    /**
     * @var PhotoAccess
     */
    private $access;

    /**
     * @var RendererInterface
     */
    private $renderer;

    public function __construct(AlbumTable $albumTable, AlbumZip $zip, PhotoAccess $access, RendererInterface $renderer)
    {
        $this->albumTable = $albumTable;
        $this->zip = $zip;
        $this->access = $access;
        $this->renderer = $renderer;
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
        if (!AlbumZip::isAvailable()) {
            return new Response(501, [], 'Téléchargement indisponible');
        }
        [$done, $total] = $this->zip->getProgress($album);
        if ($total === 0) {
            return new Response(404, ['X-Robots-Tag' => 'noindex'], 'Album vide');
        }
        if ($done < $total) {
            $this->zip->step($album);
            [$done, $total] = $this->zip->getProgress($album);
            return new Response(200, [
                'Cache-Control' => 'no-store',
                'X-Robots-Tag' => 'noindex'
            ], $this->renderer->render('@photos/zip', compact('album', 'done', 'total')));
        }
        // Sent in chunks (SapiStreamEmitter in www/index.php), not read whole into memory first
        set_time_limit(0);
        $path = $this->zip->getPath($album);
        return new Response(200, [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'attachment; filename="rockfever-' . $album->slug . '.zip"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex'
        ], Utils::streamFor(fopen($path, 'rb')));
    }
}
