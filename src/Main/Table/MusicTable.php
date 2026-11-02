<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 09-Mar-18
 */

namespace App\Main\Table;

use App\Main\Entity\Music;
use Framework\Database\Query;
use Framework\Database\Table;

class MusicTable extends Table
{

    protected $table = 'musics';

    protected $entity = Music::class;

    public function findAll(): Query
    {
        return parent::findAll()->order('m.added ASC');
    }

    public function findAllNotSeen() : Query
    {
        return parent::findAll()->where("m.added = 0")->limit(5);
    }
}
