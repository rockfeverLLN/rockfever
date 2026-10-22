<?php
namespace Framework;

use Framework\Router\MiddlewareCallback;
use Framework\Router\Route;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Route as MezzioRoute;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Router
 * Registers and matches routes
 * @package Framework
 */
class Router
{

    /**
     * @var FastRouteRouter
     */
    private $router;

    public function __construct(?string $cache = null)
    {

        $this->router = new FastRouteRouter(null, null, [
            FastRouteRouter::CONFIG_CACHE_ENABLED   => !is_null($cache),
            FastRouteRouter::CONFIG_CACHE_FILE      => $cache
        ]);
    }

    /**
     * @param string $path
     * @param string|callable $callable
     * @param string $name
     */
    public function get(string $path, $callable, ?string $name = null)
    {
        $this->router->addRoute(new MezzioRoute($path, new MiddlewareCallback($callable), ['GET'], $name));
    }

    /**
     * @param string $path
     * @param string|callable $callable
     * @param string $name
     */
    public function post(string $path, $callable, ?string $name = null)
    {
        $this->router->addRoute(new MezzioRoute($path, new MiddlewareCallback($callable), ['POST'], $name));
    }

    /**
     * @param string $path
     * @param string|callable $callable
     * @param string $name
     */
    public function delete(string $path, $callable, ?string $name = null)
    {
        $this->router->addRoute(new MezzioRoute($path, new MiddlewareCallback($callable), ['DELETE'], $name));
    }

    /**
     * @param string $prefixPath
     * @param callable $callable
     * @param string $prefixName
     */
    public function crud(string $prefixPath, $callable, string $prefixName)
    {
        $this->get("$prefixPath", $callable, "$prefixName.index");
        $this->get("$prefixPath/{id:\d+}", $callable, "$prefixName.edit");
        $this->post("$prefixPath/{id:\d+}", $callable);
        $this->get("$prefixPath/new", $callable, "$prefixName.create");
        $this->post("$prefixPath/new", $callable);
        $this->delete("$prefixPath/{id:\d+}", $callable, "$prefixName.delete");
    }

    /**
     * @param ServerRequestInterface $request
     * @return Route|null
     */
    public function match(ServerRequestInterface $request) : ?Route
    {
        $result = $this->router->match($request);
        if ($result->isSuccess()) {
            return new Route(
                $result->getMatchedRouteName(),
                $result->getMatchedRoute()->getMiddleware()->getCallback(),
                $result->getMatchedParams()
            );
        }

        return null;
    }

    public function generateUri(string $name, array $params = [], array $queryParams = []) : ?string
    {
        $uri = $this->router->generateUri($name, $params);
        if (!empty($queryParams)) {
            return $uri . '?' . http_build_query($queryParams);
        } else {
            return $uri;
        }
    }
}
