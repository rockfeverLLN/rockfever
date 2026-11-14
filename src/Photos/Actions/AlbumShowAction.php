<?php

namespace App\Photos\Actions;

use App\Photos\Entity\Album;
use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\PhotoTable;
use Framework\Database\NoRecordException;
use Framework\Renderer\RendererInterface;
use Framework\Responses\RedirectResponse;
use Framework\Router;
use Framework\Throttle;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * An album's photos, all or those of one tab (?onglet=), or the form asking for its soirée's password
 */
class AlbumShowAction
{
    /**
     * Wrong passwords allowed in a row before the form waits ATTEMPTS_DELAY seconds
     */
    const ATTEMPTS = 5;

    const ATTEMPTS_DELAY = 600;

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

    /**
     * @var Throttle
     */
    private $throttle;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        AlbumTable $albumTable,
        PhotoTable $photoTable,
        PhotoAccess $access,
        Throttle $throttle
    ) {
        $this->renderer = $renderer;
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->photoTable = $photoTable;
        $this->access = $access;
        $this->throttle = $throttle;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        try {
            $album = $this->albumTable->findBySlugForAccess($request->getAttribute('slug'));
        } catch (NoRecordException $e) {
            return new RedirectResponse($this->router->generateUri('photos'));
        }
        if ($request->getMethod() === 'POST') {
            return $this->unlock($request, $album);
        }
        if (!$this->access->canView($request, $album)) {
            return $this->passwordForm($album);
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
        $zip = class_exists(\ZipArchive::class) && $this->access->isAdmin();
        return new Response(200, PhotoIndexAction::HEADERS, $this->renderer->render(
            '@photos/album',
            compact('album', 'sections', 'section', 'photos', 'zip')
        ));
    }

    private function unlock(Request $request, Album $album) : ResponseInterface
    {
        if ($this->throttle->isBlocked($request, 'photos', self::ATTEMPTS, self::ATTEMPTS_DELAY)) {
            return $this->passwordForm($album, 'Trop d\'essais. Réessaie dans quelques minutes.');
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if (is_string($password) && $this->access->checkPassword($album, $password)) {
            $this->throttle->clear($request, 'photos');
            $url = $this->router->generateUri('photos.album', ['slug' => $album->slug]);
            return $this->access->grant(new RedirectResponse($url), $request, $album);
        }
        $this->throttle->fail($request, 'photos', self::ATTEMPTS_DELAY);
        // Slows down bots trying passwords
        sleep(1);
        return $this->passwordForm($album, 'Ce n\'est pas le bon mot de passe.');
    }

    private function passwordForm(Album $album, ?string $error = null) : ResponseInterface
    {
        return new Response(
            200,
            PhotoIndexAction::HEADERS,
            $this->renderer->render('@photos/password', compact('album', 'error'))
        );
    }
}
