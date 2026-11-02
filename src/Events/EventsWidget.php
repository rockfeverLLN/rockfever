<?php

namespace App\Events;

use App\Admin\AdminWidgetInterface;
use App\Events\Table\DancerTable;
use App\Events\Table\EventTable;
use App\Main\Calendar;
use Framework\Renderer\RendererInterface;

class EventsWidget implements AdminWidgetInterface
{

    private $colors = [
        '#1d4f86',//navy, a shade lighter than the site's so its walk-ins stay visible
        '#00897b',//teal, as the home page's buttons
        '#f0b429',//amber
        '#3b82c4',//light blue
        '#e5664a',//coral
        '#7b8794',//slate
        '#7b8794',//slate
    ];

    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var EventTable
     */
    private $eventTable;

    public function __construct(RendererInterface $renderer, EventTable $eventTable, private Calendar $calendar)
    {
        $this->renderer = $renderer;
        $this->eventTable = $eventTable;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $countForChartResult = $this->eventTable->countByEvent(6);
        $countForChart = [];
        $i = 0;
        foreach ($countForChartResult as $count) {
            $countForChart[] = [
                'id'    =>  $count->id,
                'title' =>  $count->title,
                'count' =>  $count->count,
                'improvised' => (int)$count->improvised,
                'color' =>  $this->colors[$i],
                'shade' =>  $this->darken($this->colors[$i++])
            ];
        }

        $nextEvent = $this->eventTable->findNext();
        // Its sign-up as the site shows it
        $registration = $nextEvent ? $this->calendar->find($nextEvent->getDay())['registration'] : null;
        return $this->renderer->render('@events/admin/widget', compact('countForChart', 'nextEvent', 'registration'));
    }

    /**
     * The color with each channel brought 35% closer to black
     */
    private function darken(string $hex): string
    {
        return '#' . implode('', array_map(
            fn ($channel) => sprintf('%02x', (int)round(hexdec($channel) * 0.65)),
            str_split(ltrim($hex, '#'), 2)
        ));
    }

    /**
     * @return string
     */
    public function renderMenu(): string
    {
        return $this->renderer->render('@events/admin/menu');
    }
}
