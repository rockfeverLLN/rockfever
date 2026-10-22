<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 09-Mar-18
 */

namespace App\Main\Table;

use App\Main\Entity\AboutSection;
use Framework\Database\Query;
use Framework\Database\QueryResult;
use Framework\Database\Table;

class AboutTable extends Table
{

    protected $table = 'aboutSections';

    protected $entity = AboutSection::class;

    public function findAll(): Query
    {
        return $this->makeQuery()->order('a.position ASC');
    }

    public function findMaximumPosition()
    {
        return $this->makeQuery()
            ->select('max(a.position) as maxPos')
            ->fetchColumn();
    }

    public function findHigherPositions(int $position) : QueryResult
    {
        return $this->makeQuery()
            ->where('a.position > '. $position)
            ->fetchAll();
    }
}
