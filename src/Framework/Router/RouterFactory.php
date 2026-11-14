<?php

namespace Framework\Router;

use Framework\Router;
use Psr\Container\ContainerInterface;

class RouterFactory
{

    public function __invoke(ContainerInterface $container)
    {
        $cache = null;
        if ($container->get('env') === 'production') {
            $cache = dirname(__DIR__, 3) . '/tmp/routes';
        }
        return new Router($cache);
    }
}
