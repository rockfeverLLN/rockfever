<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 14-Mar-18
 */

namespace App\Main\Actions;

use App\Auth\Role;
use App\Auth\Table\RoleTable;
use Framework\Actions\FormAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class RolesAction extends FormAction
{
    protected $messages = [
        'edit'      =>  'L\'utilisateur a bien été modifié',
        'missingDatas'=>  'Merci de bien vouloir remplir les champs label et mot de passe'
    ];

    protected $viewPath = '@auth/admin/roles';

    protected $routePrefix = 'admin.roles';

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        RoleTable $roleTable,
        FlashService $flash
    ) {
        parent::__construct($renderer, $router, $roleTable, $flash);
    }

    public function index(ServerRequestInterface $request): string
    {
        $roles = $this->table->findAll();

        return $this->renderer->render($this->viewPath . '/index', compact('roles'));
    }

    public function edit(ServerRequestInterface $request)
    {
        $role = $this->table->find($request->getParsedBody()['id']);
        if ($role) {
            $validator = $this->getValidator($request);
            if ($validator->isValid()) {
                $this->table->update($role->id, $this->getParams($request, $role));
                $this->flash->success($this->messages['edit']);
                return $this->redirect($this->routePrefix . '.index');
            } else {
                $this->flash->error($this->messages['missingDatas']);
                return $this->redirect($this->routePrefix . '.index');
            }
        }
        $roles = $this->table->findAll();
        return $this->renderer->render($this->viewPath . '/index', compact('roles'));
    }

    /**
     * @param ServerRequestInterface $request
     * @param null|Role $role
     * @return array
     */
    protected function getParams(ServerRequestInterface $request, $role = null) : array
    {
        $params = $request->getParsedBody();

        $params = array_filter($params, function ($key) {
            return in_array(
                $key,
                ['label', 'password']
            );
        }, ARRAY_FILTER_USE_KEY);
        $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
        return $params;
    }

    /**
     * @param ServerRequestInterface $request
     * @return \Framework\Validator
     */
    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('label', 'password')
            ->length('label', 2)
            ->length('password', 10);
        return $validator;
    }

    protected function getNewEntity()
    {
        $role = new Role();
        return $role;
    }
}
