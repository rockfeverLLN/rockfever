<?php
namespace App\Auth\Actions;

use App\Auth\DatabaseAuth;
use Framework\Responses\RedirectResponse;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class LogoutAction
{
    /**
     * @var DatabaseAuth
     */
    private $auth;
    /**
     * @var FlashService
     */
    private $flashService;

    public function __construct(DatabaseAuth $auth, FlashService $flashService)
    {
        $this->auth = $auth;
        $this->flashService = $flashService;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $this->auth->logout();
        $this->flashService->success('Tu as été déconnecté avec succès');
        return new RedirectResponse('/');
    }
}
