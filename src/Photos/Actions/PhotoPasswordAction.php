<?php

namespace App\Photos\Actions;

use App\Photos\PhotoAccess;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Sets the gallery's password. Visitors who gave the old one have to give the new one
 */
class PhotoPasswordAction
{
    use RouterAwareAction;

    const MIN_LENGTH = 6;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var PhotoAccess
     */
    private $access;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(Router $router, PhotoAccess $access, FlashService $flash)
    {
        $this->router = $router;
        $this->access = $access;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $password = $request->getParsedBody()['password'] ?? '';
        if (!is_string($password) || mb_strlen(trim($password)) < self::MIN_LENGTH) {
            $this->flash->error('Le mot de passe doit faire au moins ' . self::MIN_LENGTH . ' caractères');
        } else {
            $this->access->setPassword(trim($password));
            $this->flash->success(
                'Le mot de passe de la galerie a été changé. Les visiteurs devront entrer le nouveau.'
            );
        }
        return $this->redirect('admin.photos.index');
    }
}
