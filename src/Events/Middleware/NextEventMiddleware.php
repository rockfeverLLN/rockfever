<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 05-Apr-18
 */

namespace App\Events\Middleware;

use App\Events\Exception\NoNextEventException;
use App\Events\Table\EventTable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NextEventMiddleware implements MiddlewareInterface
{

    /**
     * @var EventTable
     */
    private $table;

    public function __construct(EventTable $table)
    {
        $this->table = $table;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws NoNextEventException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->table->findNext() === false) {
            throw new NoNextEventException();
        }
        return $handler->handle($request);
    }
}
