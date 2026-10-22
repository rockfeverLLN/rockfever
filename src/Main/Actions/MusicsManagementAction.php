<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 14-Mar-18
 */

namespace App\Main\Actions;

use App\Main\Entity\Value;
use App\Main\Table\ValueTable;
use Framework\Actions\FormAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class MusicsManagementAction extends FormAction
{
    protected $messages = [
        'edit'      =>  'La valeur a bien été modifiée',
        'missingDatas'=>  'Merci de bien vouloir remplir le champs'
    ];

    protected $viewPath = '@main/admin/values';

    protected $routePrefix = 'admin.values';

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        ValueTable $valueTable,
        FlashService $flash
    ) {
        parent::__construct($renderer, $router, $valueTable, $flash);
    }

    public function index(ServerRequestInterface $request): string
    {
        $values = $this->table->findAll();

        return $this->renderer->render($this->viewPath . '/index', compact('values'));
    }

    public function edit(ServerRequestInterface $request)
    {
        $value = $this->table->find($request->getParsedBody()['id']);
        if ($value) {
            $validator = $this->getValidator($request);
            if ($validator->isValid()) {
                $this->table->update($value->id, $this->getParams($request));
                $this->flash->success($this->messages['edit']);
                return $this->redirect($this->routePrefix . '.index');
            } else {
                $this->flash->error($this->messages['missingDatas']);
                return $this->redirect($this->routePrefix . '.index');
            }
        }
        $values = $this->table->findAll();
        return $this->renderer->render($this->viewPath . '/index', compact('values'));
    }

    /**
     * @param ServerRequestInterface $request
     * @param null|Value $value
     * @return array
     */
    protected function getParams(ServerRequestInterface $request, $value = null) : array
    {
        $params = $request->getParsedBody();

        $params = array_filter($params, function ($key) {
            return in_array(
                $key,
                ['value']
            );
        }, ARRAY_FILTER_USE_KEY);
        return $params;
    }

    /**
     * @param ServerRequestInterface $request
     * @return \Framework\Validator
     */
    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('value')
            ->length('value', 2);
        return $validator;
    }

    protected function getNewEntity()
    {
        $value = new Value();
        return $value;
    }
}
