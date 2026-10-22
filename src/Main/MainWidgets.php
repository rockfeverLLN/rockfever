<?php

namespace App\Main;

use App\Admin\AdminWidgetInterface;
use App\Main\Table\MusicTable;
use Framework\Renderer\RendererInterface;

class MainWidgets implements AdminWidgetInterface
{

    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var MusicTable
     */
    private $musicTable;

    public function __construct(RendererInterface $renderer, MusicTable $musicTable)
    {
        $this->renderer = $renderer;
        $this->musicTable = $musicTable;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $items = $this->musicTable->findAllNotSeen()->fetchAll();
        return $this->renderer->render('@main/admin/music/widget', compact('items'));
    }

    /**
     * @return string
     */
    public function renderMenu(): string
    {
        return $this->renderer->render('@main/admin/menu');
    }
}
