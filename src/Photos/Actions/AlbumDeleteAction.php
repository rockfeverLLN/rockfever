<?php

namespace App\Photos\Actions;

use App\Photos\PhotoUpload;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Database\NoRecordException;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Deletes an album with its photos and their files
 */
class AlbumDeleteAction
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
     * @var PhotoUpload
     */
    private $upload;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(
        Router $router,
        AlbumTable $albumTable,
        PhotoTable $photoTable,
        PhotoUpload $upload,
        FlashService $flash
    ) {
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->photoTable = $photoTable;
        $this->upload = $upload;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        try {
            $album = $this->albumTable->find((int) $request->getAttribute('id'));
        } catch (NoRecordException $e) {
            return $this->redirect('admin.photos.index');
        }
        foreach ($this->photoTable->findByAlbum($album->id)->fetchAll() as $photo) {
            $this->upload->delete($photo->filename);
        }
        // Its photos and their deletion requests go with it (ON DELETE CASCADE)
        $this->albumTable->delete($album->id);
        $this->flash->success('L\'album « ' . htmlspecialchars($album->title) . ' » et ses photos ont été supprimés');
        return $this->redirect('admin.photos.index');
    }
}
