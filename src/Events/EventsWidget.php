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
        '#f44336',//red
        '#2196f3',//blue
        '#4caf50',//green
        '#ffc107',//amber
        '#e91e63',//pink
        '#00bcd4',//cyan
        '#00bcd4',//cyan
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
                'title' =>  $count->title,
                'count' =>  $count->count,
                'color' =>  $this->colors[$i++]
            ];
        }

        $nextEvent = $this->eventTable->findNext();
        // Its sign-up as the site shows it
        $registration = $nextEvent ? $this->calendar->find($nextEvent->getDay())['registration'] : null;
        return $this->renderer->render('@events/admin/widget', compact('countForChart', 'nextEvent', 'registration'));
    }

    /**
     * @return string
     */
    public function renderMenu(): string
    {
        return $this->renderer->render('@events/admin/menu');
    }
}
