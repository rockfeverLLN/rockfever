<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Main\Entity;

#[\AllowDynamicProperties]
class AboutSection
{
    public $id;

    public $title;

    public $content;

    public $position;
}
