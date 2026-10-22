<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use Framework\Renderer\RendererInterface;
use Framework\Responses\RedirectResponse;
use Framework\Router;
use Framework\Session\SessionInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The gallery's albums, by collection, or the form asking for its password
 */
class PhotoIndexAction
{
    /**
     * Wrong passwords allowed in a row before the form waits ATTEMPTS_DELAY seconds
     */
    const ATTEMPTS = 5;

    const ATTEMPTS_DELAY = 600;

    const HEADERS = [
        // Kept out of search engines and their caches, even for whoever has the password
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'private, no-cache'
    ];

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
     * @var PhotoAccess
     */
    private $access;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        AlbumTable $albumTable,
        PhotoAccess $access,
        SessionInterface $session
    ) {
        $this->renderer = $renderer;
        $this->router = $router;
        $this->albumTable = $albumTable;
        $this->access = $access;
        $this->session = $session;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            return $this->unlock($request);
        }
        if (!$this->access->canView($request)) {
            return $this->passwordForm();
        }
        $collections = [];
        foreach ($this->albumTable->findAllWithCovers()->fetchAll() as $album) {
            if ($album->photoCount > 0) {
                $collections[$album->collection][] = $album;
            }
        }
        return new Response(200, self::HEADERS, $this->renderer->render('@photos/index', compact('collections')));
    }

    private function unlock(Request $request) : ResponseInterface
    {
        $attempts = array_filter(
            $this->session->get('photos.attempts', []),
            fn ($time) => $time > time() - self::ATTEMPTS_DELAY
        );
        if (count($attempts) >= self::ATTEMPTS) {
            return $this->passwordForm('Trop d\'essais. Réessaie dans quelques minutes.');
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if (is_string($password) && $this->access->checkPassword($password)) {
            $this->session->delete('photos.attempts');
            return $this->access->grant(new RedirectResponse($this->router->generateUri('photos')), $request);
        }
        $attempts[] = time();
        $this->session->set('photos.attempts', $attempts);
        // Slows down bots trying passwords
        sleep(1);
        return $this->passwordForm('Ce n\'est pas le bon mot de passe.');
    }

    private function passwordForm(?string $error = null) : ResponseInterface
    {
        $open = $this->access->isOpen();
        return new Response(
            200,
            self::HEADERS,
            $this->renderer->render('@photos/password', compact('open', 'error'))
        );
    }
}
