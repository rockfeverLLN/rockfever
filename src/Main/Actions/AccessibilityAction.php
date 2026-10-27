<?php

namespace App\Main\Actions;

use Framework\Renderer\RendererInterface;

class AccessibilityAction
{
    public function __construct(private RendererInterface $renderer)
    {
    }

    public function __invoke()
    {
        return $this->renderer->render('@main/accessibility');
    }
}
