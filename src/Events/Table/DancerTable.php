<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 09-Mar-18
 */

namespace App\Events\Table;

use App\Events\Entity\Dancer;
use Framework\Database\QueryResult;
use Framework\Database\Table;

class DancerTable extends Table
{

    protected $table = 'dancers';

    protected $entity = Dancer::class;

    /**
     * @param string $firstname
     * @param string $lastname
     * @param int $id
     * @throws \Framework\Database\NoRecordException
     */
    public function findDuplicates(string $firstname, string $lastname, int $id)
    {
        $this->makeQuery()
            ->where('firstname = LOWER(:firstname)', 'lastname = LOWER(:lastname)', 'event_id = :id')
            ->params([
                'firstname' =>  strtolower($firstname),
                'lastname'  =>  strtolower($lastname),
                'id'        =>  $id
            ])
            ->fetchOrFail();
    }

    /**
     * @param int $eventId
     * @return QueryResult
     */
    public function findStats(int $eventId)
    {
        return $this->makeQuery()
            ->select("status", "COUNT(id) as amount")
            ->where('event_id = :id')
            ->params([
                'id'    => $eventId
            ])
            ->groupBy("status")
            ->fetchAll();
    }

    public function findArrivalTimes(int $eventId)
    {
        return $this->makeQuery()
            ->select("count(date) as amount", "date_format(date,'%H%i') as time")
            ->where('event_id = :id', 'status > 0')
            ->params([
                'id'    =>  $eventId
            ])
            ->order('time ASC')
            ->groupBy('time')
            ->fetchAll();
    }

    public function findAllAttendees(int $eventId)
    {
        return $this->makeQuery()
            ->select('d.id', 'd.firstname', 'd.lastname', 'd.status', 'd.date')
            ->where('event_id = '.$eventId, 'status > 0')
            ->order('d.lastname ASC')
            ->fetchAll();
    }

    public function findAllRegistered(int $eventId)
    {
        return $this->makeQuery()
            ->select('d.id', 'd.firstname', 'd.lastname', 'd.status')
            ->where('event_id = '.$eventId, 'status = 0')
            ->order('d.lastname ASC')
            ->fetchAll();
    }

    public function findAllPerEvent(int $eventId)
    {
        return $this->makeQuery()
            ->select('d.id', 'd.firstname', 'd.lastname', 'd.status', 'd.date')
            ->where('event_id = '.$eventId)
            ->order('d.lastname ASC')
            ->fetchAll();
    }
}
