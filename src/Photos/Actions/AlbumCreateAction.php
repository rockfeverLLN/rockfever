<?php

namespace App\Photos\Actions;

use App\Photos\AlbumParams;
use App\Photos\Table\AlbumTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Makes an album, then opens it to add its photos
 */
class AlbumCreateAction
{
    use RouterAwareAction;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(Router $router, AlbumTable $albumTable, FlashService $flash)
    {
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $params = AlbumParams::fromRequest($request);
        if ($params === null) {
            $this->flash->error(AlbumParams::ERROR);
            return $this->redirect('admin.photos.index');
        }
        if ($params['event_id'] !== null && !$this->albumTable->eventExists($params['event_id'])) {
            $params['event_id'] = null;
        }
        $params['slug'] = $this->albumTable->makeSlug($params['title']);
        $this->albumTable->insert($params);
        $this->flash->success('L\'album a été créé : ajoutes-y les photos');
        return $this->redirect('admin.photos.album', ['id' => $this->albumTable->getPdo()->lastInsertId()]);
    }
}
