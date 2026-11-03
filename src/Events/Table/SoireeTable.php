<?php
namespace App\Events\Table;

use App\Events\Entity\Soiree;
use Framework\Database\Table;

class SoireeTable extends Table
{
    protected $entity = Soiree::class;

    protected $table = 'soirees';

    /**
     * Every date, oldest first, as App\Main\Calendar reads them: ['date' => 'Y-m-d', 'week' => …, 'cancelled' => bool]
     * @return array[]
     */
    public function findDates() : array
    {
        $dates = [];
        foreach ($this->makeQuery()->order('s.date ASC')->fetchAll() as $soiree) {
            $dates[] = [
                'id' => (int) $soiree->id,
                'date' => $soiree->date,
                'week' => $soiree->week ?: null,
                'cancelled' => (bool) $soiree->cancelled,
            ];
        }
        return $dates;
    }

    public function dateExists(string $date, ?int $exclude = null) : bool
    {
        return $this->entryExists('date', $date, $exclude);
    }
}
