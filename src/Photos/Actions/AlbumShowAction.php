<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\PhotoTable;
use Framework\Database\NoRecordException;
use Framework\Renderer\RendererInterface;
use Framework\Responses\RedirectResponse;
use Framework\Router;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * An album's photos, all or those of one tab (?onglet=)
 */
class AlbumShowAction
{
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
     * @var PhotoAccess
     */
    private $access;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        AlbumTable $albumTable,
        PhotoTable $photoTable,
        PhotoAccess $access
    ) {
        $this->renderer = $renderer;
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->photoTable = $photoTable;
        $this->access = $access;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        if (!$this->access->canView($request)) {
            return new RedirectResponse($this->router->generateUri('photos'));
        }
        try {
            $album = $this->albumTable->findBy('slug', $request->getAttribute('slug'));
        } catch (NoRecordException $e) {
            return new RedirectResponse($this->router->generateUri('photos'));
        }
        $sections = $this->photoTable->findSections($album->id);
        $section = $request->getQueryParams()['onglet'] ?? null;
        if (!in_array($section, $sections, true)) {
            $section = null;
        }
        $photos = $this->photoTable->findByAlbum($album->id);
        if ($section !== null) {
            $photos->where('p.section = :section')->params(['album' => $album->id, 'section' => $section]);
        }
        $photos = $photos->fetchAll();
        $zip = class_exists(\ZipArchive::class);
        return new Response(200, PhotoIndexAction::HEADERS, $this->renderer->render(
            '@photos/album',
            compact('album', 'sections', 'section', 'photos', 'zip')
        ));
    }
}
