<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 05-Apr-18
 */

namespace App\Events\Twig;

use App\Events\Table\EventTable;
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
            new TwigFunction('nextEventExist', [$this,'nextEventExist'])
        ];
    }

    public function nextEventExist()
    {
        return $this->table->findNext() !== false;
    }
}
