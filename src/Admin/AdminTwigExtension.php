<?php

namespace App\Admin;

class AdminTwigExtension extends \Twig\Extension\AbstractExtension
{

    /**
     * @var array
     */
    private $widgets;

    public function __construct(array $widgets)
    {
        $this->widgets = $widgets;
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions() : array
    {
        return [
            new \Twig\TwigFunction('renderMenu', [$this, 'renderMenu'], ['is_safe'=>['html']])
        ];
    }

    public function renderMenu() : string
    {
        $menu = array_reduce($this->widgets, function (string $html, AdminWidgetInterface $widget) {
            return $html . $widget->renderMenu();
        }, '');
        return $menu;
    }
}
