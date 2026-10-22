<?php

namespace App\Photos\Actions;

use App\Photos\PhotoUpload;
use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Database\NoRecordException;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Deletes the selected photos, their files, and the deletion requests about them
 */
class PhotoDeleteAction
{
    use RouterAwareAction;

    /**
     * @var Router
     */
    private $router;

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

    public function __construct(Router $router, PhotoTable $photoTable, PhotoUpload $upload, FlashService $flash)
    {
        $this->router = $router;
        $this->photoTable = $photoTable;
        $this->upload = $upload;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $ids = (array) ($request->getParsedBody()['ids'] ?? []);
        $deleted = 0;
        foreach ($ids as $id) {
            try {
                $photo = $this->photoTable->find((int) $id);
            } catch (NoRecordException $e) {
                continue;
            }
            // The requests about it go with it (ON DELETE CASCADE)
            $this->photoTable->delete($photo->id);
            $this->upload->delete($photo->filename);
            $deleted++;
        }
        if ($deleted) {
            $this->flash->success($deleted > 1 ? "$deleted photos ont été supprimées" : 'La photo a été supprimée');
        } else {
            $this->flash->error('Aucune photo sélectionnée');
        }
        // Back to the album the photos were deleted from, or to the deletion requests
        $album = (int) ($request->getParsedBody()['album'] ?? 0);
        return $album ? $this->redirect('admin.photos.album', ['id' => $album]) : $this->redirect('admin.photos.index');
    }
}
