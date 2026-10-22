<?php
/**.
 * User: Nicolas van de Walle
 * Date: 18-Feb-18
 * Time: 12:12
 */

namespace App\Blog;

use App\Admin\AdminWidgetInterface;
use App\Blog\Table\CategoryTable;
use App\Blog\Table\PostTable;
use Framework\Renderer\RendererInterface;

class BlogWidget implements AdminWidgetInterface
{

    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var PostTable
     */
    private $postTable;
    /**
     * @var CategoryTable
     */
    private $categoryTable;

    public function __construct(RendererInterface $renderer, PostTable $postTable, CategoryTable $categoryTable)
    {
        $this->renderer = $renderer;
        $this->postTable = $postTable;
        $this->categoryTable = $categoryTable;
    }

    public function render(): string
    {
        $countPosts = $this->postTable->count();
        $countCategories = $this->categoryTable->count();
        return $this->renderer->render('@blog/admin/widget', compact('countPosts', 'countCategories'));
    }

    /**
     * @return string
     */
    public function renderMenu(): string
    {
        return $this->renderer->render('@blog/admin/menu');
    }
}
