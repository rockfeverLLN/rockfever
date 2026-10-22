<?php

namespace Framework\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class MiddlewareCallback
 * Carries a route callback through Mezzio's router, which only accepts MiddlewareInterface.
 * The callback is unwrapped in Router::match() and run by the DispatcherMiddleware.
 * @package Framework\Router
 */
class MiddlewareCallback implements MiddlewareInterface
{

    /**
     * @var string|callable
     */
    private $callback;

    /**
     * @param string|callable $callback
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return string|callable
     */
    public function getCallback()
    {
        return $this->callback;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \LogicException('Route callbacks are dispatched by DispatcherMiddleware');
    }
}
