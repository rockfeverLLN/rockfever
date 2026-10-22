<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\DeletionRequestTable;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The gallery's albums, the deletion requests and the password
 */
class PhotoAdminAction
{
    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var DeletionRequestTable
     */
    private $requestTable;

    /**
     * @var PhotoAccess
     */
    private $access;

    public function __construct(
        RendererInterface $renderer,
        AlbumTable $albumTable,
        DeletionRequestTable $requestTable,
        PhotoAccess $access
    ) {
        $this->renderer = $renderer;
        $this->albumTable = $albumTable;
        $this->requestTable = $requestTable;
        $this->access = $access;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $albums = $this->albumTable->findAllWithCovers()->fetchAll();
        $requests = $this->requestTable->findAll()->fetchAll();
        $open = $this->access->isOpen();
        $collections = $this->albumTable->findCollections();
        $photographers = $this->albumTable->findPhotographers();
        $html = $this->renderer->render(
            '@photos/admin/index',
            compact('albums', 'requests', 'open', 'collections', 'photographers')
        );
        // With the gallery's cookie, the thumbnails don't each wait for the session (PhotoAccess)
        return $this->access->grant(new Response(200, [], $html), $request);
    }
}
