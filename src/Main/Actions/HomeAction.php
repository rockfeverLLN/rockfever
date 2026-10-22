<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 29-Mar-18
 */

namespace App\Main\Actions;

use App\Main\Table\AboutTable;
use Framework\Renderer\RendererInterface;

class HomeAction
{
    protected $viewPath = '@main/home';

    protected $routePrefix = 'home';
    /**
     * @var AboutTable
     */
    private $table;
    /**
     * @var RendererInterface
     */
    private $renderer;

    public function __construct(RendererInterface $renderer, AboutTable $table)
    {
        $this->table = $table;
        $this->renderer = $renderer;
    }

    public function __invoke()
    {
        $items = $this->table->findAll();

        return $this->renderer->render($this->viewPath, compact('items'));
    }
}
