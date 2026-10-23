<?php
/**.
 * User: Nicolas van de Walle
 * Date: 10-Feb-18
 * Time: 22:20
 */

namespace Framework\Renderer;

class TwigRenderer implements RendererInterface
{
    /**
     * @var \Twig\Environment
     */
    private $twig;

    public function __construct(\Twig\Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Adds a path to load views
     * @param string $namespace
     * @param null|string $path
     */
    public function addPath(string $namespace, ?string $path = null): void
    {
        /** @var \Twig\Loader\FilesystemLoader $loader the one TwigRendererFactory gives */
        $loader = $this->twig->getLoader();
        $loader->addPath($path, $namespace);
    }

    /**
     * Permits to render a view. Path can be precised by namespaces
     * $this->render('@main/home');
     * $this->render('view');
     * @param string $view
     * @param array $params
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function render(string $view, array $params = []): string
    {
        return $this->twig->render($view . '.twig', $params);
    }

    /**
     * Permits to add global vars to all views
     * @param string $key
     * @param mixed $value
     */
    public function addGlobal(string $key, $value): void
    {
        $this->twig->addGlobal($key, $value);
    }

    /**
     * @return \Twig\Environment
     */
    public function getTwig(): \Twig\Environment
    {
        return $this->twig;
    }
}
