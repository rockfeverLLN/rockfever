<?php
/**.
 * User: Nicolas van de Walle
 * Date: 10-Feb-18
 * Time: 22:15
 */

namespace Framework\Renderer;

interface RendererInterface
{
    /**
     * Adds a path to load views
     * @param string $namespace
     * @param null|string $path
     */
    public function addPath(string $namespace, ?string $path = null): void;

    /**
     * Permits to render a view. Path can be precised by namespaces
     * $this->render('@main/home');
     * $this->render('view');
     * @param string $view
     * @param array $params
     * @return string
     */
    public function render(string $view, array $params = []): string;

    /**
     * Permits to add global vars to all views
     * @param string $key
     * @param mixed $value
     */
    public function addGlobal(string $key, $value): void;
}
