<?php

namespace App\Photos;

use Framework\Auth;
use Framework\Responses\RedirectResponse;
use Framework\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The photographers' login only opens the photo albums of the admin: any other admin page, and the deletion
 * requests (the team's), lead back to them
 */
class PhotographerMiddleware implements MiddlewareInterface
{
    const ROLE = 'photographer';

    /**
     * @var Auth
     */
    private $auth;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var string
     */
    private $prefix;

    public function __construct(Auth $auth, Router $router, string $prefix)
    {
        $this->auth = $auth;
        $this->router = $router;
        $this->prefix = $prefix;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $user = $this->auth->getUser();
        if ($user && $user->getRole() === self::ROLE) {
            $path = $request->getUri()->getPath();
            $photos = $this->prefix . '/photos';
            $allowed = ($path === $photos || str_starts_with($path, $photos . '/'))
                && !str_starts_with($path, $photos . '/requests');
            if (!$allowed) {
                return new RedirectResponse($this->router->generateUri('admin.photos.index'));
            }
        }
        return $handler->handle($request);
    }
}
