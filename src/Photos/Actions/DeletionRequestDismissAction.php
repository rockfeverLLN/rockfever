<?php

namespace App\Photos\Actions;

use App\Photos\Table\DeletionRequestTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Removes a deletion request and keeps the photo
 */
class DeletionRequestDismissAction
{
    use RouterAwareAction;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var DeletionRequestTable
     */
    private $requestTable;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(Router $router, DeletionRequestTable $requestTable, FlashService $flash)
    {
        $this->router = $router;
        $this->requestTable = $requestTable;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $this->requestTable->delete((int) $request->getAttribute('id'));
        $this->flash->success('La demande a été ignorée : la photo reste dans la galerie');
        return $this->redirect('admin.photos.index');
    }
}
