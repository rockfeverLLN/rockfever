<?php
/**.
 * User: Nicolas van de Walle
 * Date: 21-Aug-18
 * Time: 20:39
 */

namespace App\Main\Actions;

use App\Main\Table\MusicTable;
use Framework\Actions\FormAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ServerRequestInterface;

class MusicAction extends FormAction
{
    protected $viewPath = '@main';

    protected $messages = [
        'create'=>  "Merci pour ta suggestion de musique !"
    ];

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        MusicTable $musicTable,
        FlashService $flash
    ) {
        parent::__construct($renderer, $router, $musicTable, $flash);
    }

    public function index(Request $request): string
    {
        return $this->renderer->render($this->viewPath . '/music');
    }

    public function edit(ServerRequestInterface $request)
    {
        $validator = $this->getValidator($request);
        if ($validator->isValid()) {
            $params = $this->getParams($request);
            $this->table->insert($params);
            $this->flash->success($this->messages['create']);
            return $this->redirect('music');
        } else {
            $this->flash->error("Merci de bien vouloir remplir le formulaire");
        }

        return $this->renderer->render($this->viewPath . '/music');
    }

    protected function getParams(ServerRequestInterface $request, $item = null): array
    {
        $params = array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['title','artist']);
        }, ARRAY_FILTER_USE_KEY);
        return $params;
    }

    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('title', 'artist')
            ->notEmpty('title');
        return $validator;
    }
}
