<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 05-Apr-18
 */

namespace App\Events\Twig;

use App\Main\Calendar;
use Twig\TwigFilter;
use Twig\TwigFunction;

class EventsExtension extends \Twig\Extension\AbstractExtension
{
    public function __construct(private Calendar $calendar)
    {
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('nextEventExist', [$this,'nextEventExist']),
            new TwigFunction('nextSoiree', [$this,'nextSoiree']),
            // The soirées as schema.org events, in a <script> for the page's head
            new TwigFunction('soireesJsonLd', [$this, 'soireesJsonLd'], ['is_safe' => ['html']])
        ];
    }

    public function getFilters() : array
    {
        return [
            // "mercredi 4 novembre"
            new TwigFilter('dayLabel', fn (\DateTimeInterface $date) => mb_strtolower(Calendar::label($date)))
        ];
    }

    public function nextEventExist()
    {
        return $this->calendar->getNext() !== null;
    }

    /**
     * The next soirée (Calendar::getNext), or null when none is announced
     */
    public function nextSoiree() : ?array
    {
        return $this->calendar->getNext();
    }

    public function soireesJsonLd(array $soirees) : string
    {
        // Twig's filter keeps the keys: a list, so that several events stay a JSON array
        $events = array_map([$this->calendar, 'toSchema'], array_values($soirees));
        if (!$events) {
            return '';
        }
        return '<script type="application/ld+json">'
            . json_encode(count($events) === 1 ? $events[0] : $events, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }
}
