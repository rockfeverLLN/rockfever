<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 05-Apr-18
 */

namespace App\Events\Twig;

use App\Events\Table\EventTable;
use App\Main\Calendar;
use Twig\TwigFilter;
use Twig\TwigFunction;

class EventsExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @var EventTable
     */
    private $table;

    public function __construct(EventTable $table)
    {
        $this->table = $table;
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('nextEventExist', [$this,'nextEventExist']),
            new TwigFunction('nextEvent', [$this,'nextEvent'])
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
        return $this->table->findNext() !== false;
    }

    /**
     * The next event of the admin (today or later), or null when none is announced
     */
    public function nextEvent()
    {
        return $this->table->findNext() ?: null;
    }
}
