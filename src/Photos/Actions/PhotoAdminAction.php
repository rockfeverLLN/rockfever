<?php

namespace App\Photos\Actions;

use App\Photos\Table\AlbumTable;
use App\Photos\Table\DeletionRequestTable;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The gallery's albums and the deletion requests
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

    public function __construct(
        RendererInterface $renderer,
        AlbumTable $albumTable,
        DeletionRequestTable $requestTable
    ) {
        $this->renderer = $renderer;
        $this->albumTable = $albumTable;
        $this->requestTable = $requestTable;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $albums = $this->albumTable->findAllWithCovers()->fetchAll();
        $requests = $this->requestTable->findAll()->fetchAll();
        $collections = $this->albumTable->findCollections();
        $photographers = $this->albumTable->findPhotographers();
        $events = $this->albumTable->findEvents();
        // Suggested for a new album: the last soirée that already took place
        $today = date('Y-m-d');
        $event = current(array_filter($events, fn ($event) => $event->date <= $today)) ?: null;
        return new Response(200, [], $this->renderer->render(
            '@photos/admin/index',
            compact('albums', 'requests', 'collections', 'photographers', 'events', 'event')
        ));
    }
}
