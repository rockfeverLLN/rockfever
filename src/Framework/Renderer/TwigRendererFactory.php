<?php
/**.
 * User: Nicolas van de Walle
 * Date: 11-Feb-18
 * Time: 09:30
 */

namespace Framework\Renderer;

use Psr\Container\ContainerInterface;
use Twig\Extension\DebugExtension;

class TwigRendererFactory
{
    public function __invoke(ContainerInterface $container) : TwigRenderer
    {
        $debug = $env = $container->get('env') !== 'production';

        $viewPath = $container->get('views.path');
        $loader = new \Twig\Loader\FilesystemLoader($viewPath);
        $twig = new \Twig\Environment($loader, [
            'debug' => $debug,
            'cache' => $debug ? false : dirname(__DIR__, 3) . '/tmp/views',
            'auto_reload' => $debug
        ]);
        $twig->addExtension(new DebugExtension());
        if ($container->has('twig.extensions')) {
            foreach ($container->get('twig.extensions') as $extension) {
                $twig->addExtension($extension);
            }
        }
        
        return new TwigRenderer($twig);
    }
}
