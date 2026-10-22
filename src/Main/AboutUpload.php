<?php
/**.
 * User: Nicolas van de Walle
 * Date: 22-Aug-18
 * Time: 18:18
 */

namespace App\Main;

use Framework\Upload;

class AboutUpload extends Upload
{
    protected $path = 'www/images/about';

    protected $maxSize = 1600;

    protected $widths = [320, 640, 960, 1280];
}
