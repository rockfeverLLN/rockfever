<?php
namespace App\Auth\Actions;

use App\Auth\Table\RoleTable;
use Framework\Renderer\RendererInterface;
use Psr\Http\Message\ServerRequestInterface;

class LoginAction
{
    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var RoleTable
     */
    private $roleTable;

    public function __construct(RendererInterface $renderer, RoleTable $roleTable)
    {
        $this->renderer = $renderer;
        $this->roleTable = $roleTable;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return $this->renderer->render('@auth/login', ["roles" => $this->roleTable->findList()]);
    }
}
