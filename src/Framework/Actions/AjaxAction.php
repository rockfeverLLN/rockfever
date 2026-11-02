<?php
namespace Framework\Actions;

use Framework\Database\Table;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class AjaxAction
{

    /** @var Table */
    protected $table;

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
}
