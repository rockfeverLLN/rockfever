<?php

namespace App\Events;

use App\Admin\AdminWidgetInterface;
use App\Events\Table\EventTable;
use App\Main\Calendar;
use Framework\Renderer\RendererInterface;

class EventsWidget implements AdminWidgetInterface
{

    /**
     * How long after its end a soirée is still "ce soir" on the card: until 7:00 when it ends at 1:00
     */
    private const AFTER_END = '+6 hours';

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

        $now = $this->now();
        $today = $now->format('Y-m-d');
        // Last night's soirée stays on the card after midnight, until the morning after it ends
        $yesterday = $this->calendar->find($now->modify('-1 day')->format('Y-m-d'));
        $from = $yesterday && $this->stillTonight($yesterday, $now) ? $yesterday['date'] : $today;

        $nextEvent = $this->eventTable->findNext($from);
        // Its soirée and sign-up as the site shows them: times, place and prices of the settings unless the event changes them
        $soiree = $nextEvent ? $this->calendar->find($nextEvent->getDay()) : null;
        $registration = $soiree['registration'] ?? null;
        // "Ce soir" from the morning of its day until the morning after
        $tonight = $soiree && $soiree['date'] <= $today && $this->stillTonight($soiree, $now);
        return $this->renderer->render('@events/admin/widget', compact('countForChart', 'nextEvent', 'soiree', 'registration', 'tonight'));
    }

    protected function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(Calendar::TIMEZONE));
    }

    /**
     * Whether the soirée, not cancelled, ended less than AFTER_END ago (or is still to end)
     */
    private function stillTonight(array $soiree, \DateTimeImmutable $now): bool
    {
        return !$soiree['cancelled'] && $now < $soiree['end']->modify(self::AFTER_END);
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
