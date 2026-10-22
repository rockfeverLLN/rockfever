<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Events\Entity;

#[\AllowDynamicProperties]
class Dancer
{
    public $id;

    public $firstname;

    public $lastname;

    public $event_id;

    public $date;
}
