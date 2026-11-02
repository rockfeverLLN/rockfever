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
 * Sends a photo of the gallery (storage/photos isn't in the web root), at one of PhotoUpload's widths or as uploaded
 */
class PhotoFileAction
{
    const TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'jxl' => 'image/jxl'];

    /**
     * @var PhotoTable
     */
    private $photoTable;

    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var PhotoUpload
     */
    private $upload;

    /**
     * @var PhotoAccess
     */
    private $access;

    public function __construct(PhotoTable $photoTable, AlbumTable $albumTable, PhotoUpload $upload, PhotoAccess $access)
    {
        $this->photoTable = $photoTable;
        $this->albumTable = $albumTable;
        $this->upload = $upload;
        $this->access = $access;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        try {
            $photo = $this->photoTable->find((int) $request->getAttribute('id'));
            $album = $this->albumTable->findForAccess((int) $photo->albumId);
        } catch (NoRecordException $e) {
            return new Response(404, ['X-Robots-Tag' => 'noindex'], 'Photo introuvable');
        }
        if (!$this->access->canView($request, $album)) {
            return new Response(403, ['X-Robots-Tag' => 'noindex'], 'Accès refusé');
        }
        $original = $this->upload->getPath() . '/' . $photo->filename;
        $download = isset($request->getQueryParams()['download']);
        $file = $download ? $original : $this->getFile($original, $request->getAttribute('size'));
        if (!is_file($file)) {
            return new Response(404, ['X-Robots-Tag' => 'noindex'], 'Photo introuvable');
        }
        // Browsers that can show JPEG XL get its copy when there is one. A download is always the photo as uploaded
        $jxl = PhotoUpload::getJxlPath($file);
        if (!$download && str_contains($request->getHeaderLine('Accept'), 'image/jxl') && is_file($jxl)) {
            $file = $jxl;
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $headers = [
            'Content-Type' => self::TYPES[$extension] ?? 'application/octet-stream',
            'Content-Length' => (string) filesize($file),
            // A photo never changes under its URL: deleting it removes the URL
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Robots-Tag' => 'noindex'
        ];
        if ($download) {
            $headers['Content-Disposition'] = 'attachment; filename="rockfever-' . $photo->id . '.' . $extension . '"';
        } else {
            $headers['Vary'] = 'Accept';
        }
        return new Response(200, $headers, Utils::streamFor(fopen($file, 'rb')));
    }

    /**
     * The copy at that width, or the next larger one: photos smaller than a width have no copy for it
     */
    private function getFile(string $original, string $size) : string
    {
        if ($size !== 'full') {
            foreach ($this->upload->getWidths() as $width) {
                $file = PhotoUpload::getResizedPath($original, $width);
                if ($width >= (int) $size && is_file($file)) {
                    return $file;
                }
            }
        }
        return $original;
    }
}
