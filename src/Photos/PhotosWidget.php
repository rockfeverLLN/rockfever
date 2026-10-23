<?php

namespace App\Photos;

use App\Admin\AdminWidgetInterface;
use App\Photos\Table\DeletionRequestTable;
use Framework\Renderer\RendererInterface;

class PhotosWidget implements AdminWidgetInterface
{
    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var DeletionRequestTable
     */
    private $requestTable;

    public function __construct(RendererInterface $renderer, DeletionRequestTable $requestTable)
    {
        $this->renderer = $renderer;
        $this->requestTable = $requestTable;
    }

    /**
     * Only shown while photos wait for a deletion request to be handled
     */
    public function render() : string
    {
        $count = $this->requestTable->count();
        if ($count === 0) {
            return '';
        }
        return $this->renderer->render('@photos/admin/widget', compact('count'));
    }

    public function renderMenu() : string
    {
        return $this->renderer->render('@photos/admin/menu');
    }
}
