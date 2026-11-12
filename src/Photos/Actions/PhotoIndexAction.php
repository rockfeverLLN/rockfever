<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The albums, by collection. Those the visitor hasn't opened with their soirée's password show a lock, not a photo
 */
class PhotoIndexAction
{
    const HEADERS = [
        // Kept out of search engines and their caches, even for whoever has the password
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'private, no-cache'
    ];

    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var PhotoAccess
     */
    private $access;

    public function __construct(RendererInterface $renderer, AlbumTable $albumTable, PhotoAccess $access)
    {
        $this->renderer = $renderer;
        $this->albumTable = $albumTable;
        $this->access = $access;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $collections = [];
        $unlocked = [];
        foreach ($this->albumTable->findAllWithCovers()->fetchAll() as $album) {
            // Albums without photos aren't listed
            if ($album->photoCount > 0) {
                $collections[$album->collection][] = $album;
                if ($this->access->canView($request, $album)) {
                    $unlocked[] = $album->id;
                }
            }
        }
        return new Response(
            200,
            self::HEADERS,
            $this->renderer->render('@photos/index', compact('collections', 'unlocked'))
        );
    }
}
