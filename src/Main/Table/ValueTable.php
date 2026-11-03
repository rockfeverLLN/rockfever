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

    /**
     * Every saved value, for App\Main\Settings
     * @return array<string, string> name => value
     */
    public function findValues() : array
    {
        $values = [];
        foreach ($this->getPdo()->query('SELECT name, value FROM `values`')->fetchAll(\PDO::FETCH_NUM) as [$name, $value]) {
            $values[$name] = (string) $value;
        }
        return $values;
    }

    /**
     * Saves a value, adding its row the first time
     */
    public function setValue(string $name, string $value, string $label, string $type) : void
    {
        $id = $this->getPdo()->prepare('SELECT id FROM `values` WHERE name = ?');
        $id->execute([$name]);
        $id = $id->fetchColumn();
        if ($id === false) {
            $this->insert(compact('name', 'value', 'label', 'type'));
        } else {
            $this->update((int) $id, ['value' => $value]);
        }
    }

    public function findAll(): Query
    {
        return parent::findAll()->order("v.label ASC");
    }
}
