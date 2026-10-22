<?php
/**.
 * User: Nicolas van de Walle
 * Date: 23-Aug-18
 * Time: 16:26
 */

namespace Framework\Middleware;

use Framework\Exception\CsrfInvalidException;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExceptionCatcherMiddleware implements MiddlewareInterface
{

    /**
     * @var RendererInterface
     */
    private $renderer;

    public function __construct(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (CsrfInvalidException $e) {
            return new Response(
                403,
                [],
                $this->renderer->render("403")
            );
        } catch (\Exception $e) {
            return new Response(
                500,
                [],
                $this->renderer->render("500")
            );
        }
    }
}
