<?php

namespace App\Photos\Actions;

use App\Photos\AlbumParams;
use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\DeletionRequestTable;
use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Database\NoRecordException;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * An album in the admin: add and delete its photos, change its title, collection or photographer
 */
class AlbumAdminAction
{
    use RouterAwareAction;

    /**
     * @var RendererInterface
     */
    private $renderer;

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
     * @var DeletionRequestTable
     */
    private $requestTable;

    /**
     * @var PhotoAccess
     */
    private $access;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        AlbumTable $albumTable,
        PhotoTable $photoTable,
        DeletionRequestTable $requestTable,
        PhotoAccess $access,
        FlashService $flash
    ) {
        $this->renderer = $renderer;
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->photoTable = $photoTable;
        $this->requestTable = $requestTable;
        $this->access = $access;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        try {
            $album = $this->albumTable->find((int) $request->getAttribute('id'));
        } catch (NoRecordException $e) {
            $this->flash->error('Cet album n\'existe plus');
            return $this->redirect('admin.photos.index');
        }
        if ($request->getMethod() === 'POST') {
            $params = AlbumParams::fromRequest($request);
            if ($params === null) {
                $this->flash->error('Donne un titre et une collection à l\'album (100 caractères au plus)');
            } else {
                $params['slug'] = $this->albumTable->makeSlug($params['title'], $album->id);
                $this->albumTable->update($album->id, $params);
                $this->flash->success('L\'album a été modifié');
            }
            return $this->redirect('admin.photos.album', ['id' => $album->id]);
        }
        $photos = $this->photoTable->findByAlbum($album->id)->fetchAll();
        $sections = $this->photoTable->findSections($album->id);
        $requested = array_map(fn ($r) => $r->photoId, iterator_to_array($this->requestTable->findAll()->fetchAll()));
        $collections = $this->albumTable->findCollections();
        $photographers = $this->albumTable->findPhotographers();
        $html = $this->renderer->render(
            '@photos/admin/album',
            compact('album', 'photos', 'sections', 'requested', 'collections', 'photographers')
        );
        // With the gallery's cookie, the thumbnails don't each wait for the session (PhotoAccess)
        return $this->access->grant(new Response(200, [], $html), $request);
    }
}
