<?php

namespace App\Photos;

use App\Admin\AdminWidgetInterface;
use Framework\Renderer\RendererInterface;

/**
 * The progress bar of the JPEG XL copies on the dashboard, which the photographers' login never opens. Only while some
 * are missing: uploads make their own copy (Framework\Upload), the card catches up the ones that failed
 */
class JxlWidget implements AdminWidgetInterface
{
    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var JxlProgress
     */
    private $progress;

    public function __construct(RendererInterface $renderer, JxlProgress $progress)
    {
        $this->renderer = $renderer;
        $this->progress = $progress;
    }

    public function render() : string
    {
        $status = $this->progress->status();
        if ($status['done'] >= $status['total']) {
            return '';
        }
        return $this->renderer->render('@photos/admin/jxl-widget', $status);
    }

    public function renderMenu() : string
    {
        return '';
    }
}
