<?php
namespace Framework\Actions;

use Framework\Database\Hydrator;
use Framework\Database\NoRecordException;
use Framework\Database\Table;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class CrudAction
{

    /** @var RendererInterface */
    public $renderer;

    /** @var Router */
    private $router;

    /** @var Table */
    protected $table;
    /** @var FlashService */
    protected $flash;

    /** @var string */
    protected $viewPath;

    /** @var string */
    protected $routePrefix;

    /** @var string[] */
    protected $messages = [
        'edit'  =>  "Item has been edited with success !",
        'create'=>  "Item has been created with success !",
        'delete'=>  "Item has been deleted with success !"
    ];

    use RouterAwareAction;

    /**
     * BlogAction constructor.
     * @param RendererInterface $renderer
     * @param Router $router
     * @param $table
     * @param FlashService $flash
     */
    public function __construct(
        RendererInterface $renderer,
        Router $router,
        Table $table,
        FlashService $flash
    ) {
        $this->renderer = $renderer;
        $this->router = $router;
        $this->table = $table;
        $this->flash = $flash;
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     * @throws NoRecordException
     */
    public function __invoke(Request $request)
    {
        $this->renderer->addGlobal('viewPath', $this->viewPath);
        $this->renderer->addGlobal('routePrefix', $this->routePrefix);
        if ($request->getMethod() === 'DELETE') {
            return $this->delete($request);
        }
        if (substr((string) $request->getUri(), -3) === 'new') {
            return $this->create($request);
        }
        if ($request->getAttribute('id')) {
            return $this->edit($request);
        } else {
            return $this->index($request);
        }
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function index(Request $request) : string
    {
        $params = $request->getQueryParams();
        $items = $this->table->findAll()->paginate(10, $params['p'] ?? 1);

        return $this->renderer->render($this->viewPath . '/index', compact('items'));
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     * @throws NoRecordException
     */
    public function edit(Request $request)
    {
        $item = $this->table->find($request->getAttribute('id'));
        $errors = [];
        if ($request->getMethod() === "POST") {
            $validator = $this->getValidator($request);
            if ($validator->isValid()) {
                $this->table->update($item->id, $this->getParams($request, $item));
                $this->flash->success($this->messages['edit']);
                return $this->redirect($this->routePrefix . '.index');
            }
            $errors = $validator->getErrors();
            Hydrator::hydrate($request->getParsedBody(), $item);
        }

        return $this->renderer->render(
            $this->viewPath . '/edit',
            $this->formParams(compact('item', 'errors'))
        );
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function create(Request $request)
    {
        $item = $this->getNewEntity();
        $errors = [];
        if ($request->getMethod() === "POST") {
            $validator = $this->getValidator($request);
            if ($validator->isValid()) {
                $this->table->insert($this->getParams($request, $item));
                $this->flash->success($this->messages['create']);
                return $this->redirect($this->routePrefix . '.index');
            }
            Hydrator::hydrate($request->getParsedBody(), $item);
            $errors = $validator->getErrors();
        }

        return $this->renderer->render(
            $this->viewPath . '/create',
            $this->formParams(compact('item', 'errors'))
        );
    }

    /**
     * @param Request $request
     * @return ResponseInterface
     */
    public function delete(Request $request)
    {
        $this->table->delete($request->getAttribute('id'));
        $this->flash->success($this->messages['delete']);
        return $this->redirect($this->routePrefix . '.index');
    }

    /**
     * @param Request $request
     * @param $item
     * @return array
     */
    protected function getParams(Request $request, $item) : array
    {
        return array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, []);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param Request $request
     * @return Validator
     */
    protected function getValidator(Request $request)
    {
        return new Validator(array_merge($request->getParsedBody(), $request->getUploadedFiles()));
    }

    /**
     * @return mixed
     */
    protected function getNewEntity()
    {
        return [];
    }

    /**
     * Manages the params that must be sent to the view
     * @param array $params
     * @return array
     */
    protected function formParams(array $params) : array
    {
        return $params;
    }
}
