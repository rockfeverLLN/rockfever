<?php
namespace Framework\Actions;

use Framework\Database\Table;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class FormAction
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
     * FormAction constructor.
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
     */
    public function __invoke(Request $request)
    {
        $this->renderer->addGlobal('viewPath', $this->viewPath);
        $this->renderer->addGlobal('routePrefix', $this->routePrefix);
        if ($request->getMethod() === "POST") {
            return $this->edit($request);
        }
        return $this->index($request);
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function index(Request $request) : string
    {
        return $this->renderer->render($this->viewPath . '/index');
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function edit(Request $request)
    {
        return $this->renderer->render(
            $this->viewPath . '/index',
            $this->formParams([])
        );
    }

    /**
     * @param Request $request
     * @param null $item
     * @return array
     */
    protected function getParams(Request $request, $item = null) : array
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
