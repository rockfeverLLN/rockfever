<?php
namespace Framework\Actions;

use Framework\Database\Table;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class AjaxAction
{

    /** @var Table */
    protected $table;

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
     * The answer to a GET. Each action's route only takes one method: the other one isn't reachable
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function index(Request $request)
    {
        return new Response(405);
    }

    /**
     * The answer to a POST
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function edit(Request $request)
    {
        return new Response(405);
    }
}
