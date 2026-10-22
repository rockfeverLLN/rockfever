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
        // The sections after the one titled "Le Staff" each present a member: the page shows them side by side as cards
        $items = [];
        $members = [];
        $staffFound = false;
        foreach ($this->table->findAll() as $item) {
            if ($this->isEmpty($item->content) && trim($item->title) === '') {
                continue;
            }
            if ($staffFound) {
                $members[] = $item;
                continue;
            }
            $items[] = $item;
            $staffFound = mb_strtolower(trim($item->title)) === 'le staff';
        }

        return $this->renderer->render($this->viewPath, compact('items', 'members'));
    }

    /**
     * True when a section shows nothing: only blank paragraphs, comments (e.g. a disabled video) or spaces
     */
    private function isEmpty(string $content) : bool
    {
        $visible = strip_tags($content, '<img><iframe><video>');
        return trim(str_replace(["\u{a0}", '&nbsp;'], ' ', $visible)) === '';
    }
}
