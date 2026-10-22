<?php
/**.
 * User: Nicolas van de Walle
 * Date: 25-Feb-18
 * Time: 14:07
 */

namespace App\Auth\Table;

use App\Auth\Role;
use Framework\Database\Table;

class RoleTable extends Table
{
    protected $table = "roles";

    protected $entity = Role::class;

    public function findList() : array
    {
        $results = $this->getPdo()
            ->query("SELECT role, label FROM {$this->table}")
            ->fetchAll(\PDO::FETCH_NUM);
        $list = [];
        foreach ($results as $result) {
            $list[$result[0]] = $result[1];
        }
        return $list;
    }
}
