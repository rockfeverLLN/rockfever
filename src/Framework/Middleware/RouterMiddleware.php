<?php
/**.
 * User: Nicolas van de Walle
 * Date: 18-Feb-18
 * Time: 20:44
 */

namespace Framework\Middleware;

use Framework\Router;
use Psr\Http\Message\ServerRequestInterface;

class RouterMiddleware
{

    /**
     * @var Router
     */
    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * @param ServerRequestInterface $request
     * @param callable $next
     * @return mixed
     */
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $route = $this->router->match($request);

        if (is_null($route)) {
            return $next($request);
        }

        $params = $route->getParams();

        $request = array_reduce(array_keys($params), function (ServerRequestInterface $request, $key) use ($params) {
            return $request->withAttribute($key, $params[$key]);
        }, $request);

        $request = $request->withAttribute(get_class($route), $route);
        return $next($request);
    }
}
