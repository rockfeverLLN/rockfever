<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use App\Photos\Table\DeletionRequestTable;
use App\Photos\Table\PhotoTable;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Upload and delete the gallery's photos, handle the deletion requests and set the password
 */
class PhotoAdminAction
{
    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var PhotoTable
     */
    private $photoTable;

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
        PhotoTable $photoTable,
        DeletionRequestTable $requestTable,
        PhotoAccess $access
    ) {
        $this->renderer = $renderer;
        $this->photoTable = $photoTable;
        $this->requestTable = $requestTable;
        $this->access = $access;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $photos = $this->photoTable->findAll()->fetchAll();
        $requests = $this->requestTable->findAll()->fetchAll();
        $open = $this->access->isOpen();
        $html = $this->renderer->render('@photos/admin/index', compact('photos', 'requests', 'open'));
        // With the gallery's cookie, the thumbnails don't each wait for the session (PhotoAccess)
        return $this->access->grant(new Response(200, [], $html), $request);
    }
}
