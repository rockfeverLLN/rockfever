<?php
/**.
 * User: Nicolas van de Walle
 * Date: 11-Feb-18
 * Time: 14:35
 */

namespace Framework\Router;

use Framework\Router;
use Psr\Http\Message\ServerRequestInterface;

class RouterTwigExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @var Router
     */
    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('path', [$this, 'pathFor']),
            new \Twig\TwigFunction('isSubPath', [$this, 'isSubPath'])
        ];
    }

    public function pathFor(string $path, array $params = []) : string
    {
        return $this->router->generateUri($path, $params);
    }

    public function isSubPath(string $route) : bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $expectedUri = $this->router->generateUri($route);
        if ($route === "home" || $route === "admin.dashboard") {
            return $uri === $expectedUri;
        }
        return strpos($uri, $expectedUri) !== false;
    }
}
