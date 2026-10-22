<?php
namespace Framework\Actions;

use Framework\Database\Table;
use Framework\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class AjaxAction
{

    /** @var Table */
    protected $table;

    /** @var string */
    protected $routePrefix;

    use RouterAwareAction;

    /**
     * @param Table $table
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function __invoke(Request $request)
    {
        if ($request->getMethod() === "POST") {
            return $this->edit($request);
        }
        return $this->index($request);
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    abstract public function index(Request $request);

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    abstract public function edit(Request $request);

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
}
