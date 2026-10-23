<?php

namespace App\Photos\Actions;

use App\Photos\Table\AlbumTable;
use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Database\NoRecordException;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Makes one of an album's photos its cover on /photos
 */
class AlbumCoverAction
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
     * @var PhotoTable
     */
    private $photoTable;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(Router $router, AlbumTable $albumTable, PhotoTable $photoTable, FlashService $flash)
    {
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->photoTable = $photoTable;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $albumId = (int) $request->getAttribute('id');
        try {
            $photo = $this->photoTable->find((int) ($request->getParsedBody()['photo_id'] ?? 0));
        } catch (NoRecordException $e) {
            $photo = null;
        }
        if (!$photo || (int) $photo->albumId !== $albumId) {
            $this->flash->error('Cette photo n\'est pas dans l\'album');
        } else {
            $this->albumTable->update($albumId, ['cover_photo_id' => $photo->id]);
            $this->flash->success('La couverture de l\'album a été changée');
        }
        return $this->redirect('admin.photos.album', ['id' => $albumId]);
    }
}
