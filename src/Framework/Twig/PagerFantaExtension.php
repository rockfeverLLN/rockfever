<?php
namespace Framework\Twig;

use Framework\Router;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\DefaultView;
use Pagerfanta\View\TwitterBootstrap4View;

class PagerFantaExtension extends \Twig\Extension\AbstractExtension
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
            new \Twig\TwigFunction('paginate', [$this,'paginate'], ['is_safe' => ['html']])
        ];
    }

    /**
     * @param Pagerfanta $paginatedResults
     * @param string $route
     * @param array $routerParams
     * @param array $queryArgs
     * @return string
     */
    public function paginate(
        Pagerfanta $paginatedResults,
        string $route,
        array $routerParams = [],
        array $queryArgs = []
    ) : string {
        $view = new TwitterBootstrap4View();
        return $view->render($paginatedResults, function (int $page) use ($route, $routerParams, $queryArgs) {
            if ($page > 1) {
                $queryArgs['p'] = $page;
            }
            return $this->router->generateUri($route, $routerParams, ['p' => $page]);
        }, [
            'prev_message'      => '<i class="material-icons">chevron_left</i>',
            'next_message'          => '<i class="material-icons">chevron_right</i>',
            'css_container_class'   => 'pagination center',
            'css_prev_class'        => '',
            'css_next_class'        => ''
        ]);
    }
}
