<?php
/**.
 * User: Nicolas van de Walle
 * Date: 22-Aug-18
 * Time: 18:18
 */

namespace App\Main;

use Framework\Upload;
use Psr\Http\Message\UploadedFileInterface;

class AboutUpload extends Upload
{
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    protected $path = 'www/images/about'; // made absolute by Upload::__construct

    protected $maxSize = 1600;

    protected $widths = [320, 640, 800, 960, 1280];

    /**
     * Only images: the files land in the web root, where a .php file would run
     */
    public function upload(UploadedFileInterface $file, ?string $oldFile = null) : ?string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return null;
        }
        $extension = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
        $size = @getimagesize($file->getStream()->getMetadata('uri'));
        if (!in_array($extension, self::EXTENSIONS, true)
            || !$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            return null;
        }
        return parent::upload($file, $oldFile);
    }
}
