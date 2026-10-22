<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Main\Entity;

#[\AllowDynamicProperties]
class Music
{
    public $id;

    public $title;

    public $artist;

    public $added;
}
