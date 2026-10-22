<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 24-Mar-18
 */

namespace App\Main\Table;

use App\Main\Entity\Value;
use Framework\Database\NoRecordException;
use Framework\Database\Query;
use Framework\Database\Table;

class ValueTable extends Table
{

    protected $entity = Value::class;

    protected $table = 'values';

    /**
     * @param string $name
     * @return string
     * @throws NoRecordException
     */
    public function getValue(string $name) : string
    {
        return $this->findBy("name", $name)->value;
    }

    public function findAll(): Query
    {
        // The photo gallery's password is stored hashed and set on /admin/photos, not as a plain value here
        return parent::findAll()->where("v.type != 'password'")->order("v.label ASC");
    }
}
