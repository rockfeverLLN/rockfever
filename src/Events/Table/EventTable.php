<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 03-Mar-18
 */

namespace App\Events\Table;

use App\Events\Entity\Event;
use Framework\Database\Query;
use Framework\Database\QueryResult;
use Framework\Database\Table;

class EventTable extends Table
{

    protected $entity = Event::class;

    protected $table = 'events';

    public function findAll(): Query
    {
        $dancers = new DancerTable($this->getPdo());
        return $this->makeQuery()
            ->select('e.*', 'count(d.id) as participantsCount')
            ->join($dancers->getTable() . ' as d', 'd.event_id = e.id')
            ->groupBy('e.id')
            ->order('e.date DESC');
    }

    public function countByEvent(int $maxResults)
    {
        $queryResults = $this->makeQuery()
            ->select('e.id', 'e.title', 'count(d.id) as count', 'sum(d.status = 3) as improvised')
            ->where('d.status != 0', 'e.date < NOW()')
            ->join('dancers as d', 'd.event_id = e.id')
            ->groupBy('e.id')
            ->order('e.date DESC')
            ->fetchAll();
        $results = [];
        for ($i=0; $queryResults->offsetExists($i) && $i < $maxResults; $i++) {
            $results[] = $queryResults->get($i);
        }
        return array_reverse($results);
    }

    /**
     * Every event, oldest first (App\Main\Calendar matches them with the soirées by day)
     */
    public function findAllByDay() : QueryResult
    {
        return $this->makeQuery()->order('e.date ASC, e.id ASC')->fetchAll();
    }

    public function findNext()
    {
        return $this->makeQuery()
            ->select('e.*, count(d.id) as participantsCount')
            ->join('dancers as d', 'd.event_id = e.id')
            ->where('e.date >= CURDATE()')
            ->order('e.date ASC')
            ->groupBy('e.id')
            ->fetch();
    }
}
