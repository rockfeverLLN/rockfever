<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 05-Apr-18
 */

namespace App\Events\Middleware;

use App\Events\Exception\NoNextEventException;
use App\Main\Calendar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NextEventMiddleware implements MiddlewareInterface
{

    public function __construct(private Calendar $calendar)
    {
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
        if ($this->calendar->getNext() === null) {
            throw new NoNextEventException();
        }
        return $handler->handle($request);
    }
}
