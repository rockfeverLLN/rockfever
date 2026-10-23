<?php

namespace App\Photos\Actions;

use App\Photos\PhotoUpload;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Adds photos to an album, in one of its tabs if one is given. The admin page's script sends them one by one (so no request is over the server's
 * size limit) and reads the JSON answer; without it, the form sends them all at once
 */
class PhotoUploadAction
{
    use RouterAwareAction;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var PhotoTable
     */
    private $photoTable;

    /**
     * @var AlbumTable
     */
    private $albumTable;

    /**
     * @var PhotoUpload
     */
    private $upload;

    /**
     * @var FlashService
     */
    private $flash;

    public function __construct(
        Router $router,
        PhotoTable $photoTable,
        AlbumTable $albumTable,
        PhotoUpload $upload,
        FlashService $flash
    ) {
        $this->router = $router;
        $this->photoTable = $photoTable;
        $this->albumTable = $albumTable;
        $this->upload = $upload;
        $this->flash = $flash;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $albumId = (int) $request->getAttribute('id');
        if (!$this->albumTable->exists($albumId)) {
            $this->flash->error('Cet album n\'existe plus');
            return $this->redirect('admin.photos.index');
        }
        $section = $request->getParsedBody()['section'] ?? '';
        $section = is_string($section) && trim($section) !== '' ? mb_substr(trim($section), 0, 100) : null;
        $files = $request->getUploadedFiles()['photos'] ?? [];
        $files = is_array($files) ? $files : [$files];
        $added = 0;
        $errors = [];
        /** @var UploadedFileInterface $file */
        foreach ($files as $file) {
            $name = $file->getClientFilename();
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
                $errors[] = "$name est trop lourde";
                continue;
            }
            $photo = $file->getError() === UPLOAD_ERR_OK ? $this->upload->uploadPhoto($file) : null;
            if ($photo === null) {
                $errors[] = "$name n'est pas une photo JPEG, PNG ou WebP";
                continue;
            }
            [$filename, $width, $height] = $photo;
            $this->photoTable->insert([
                'album_id' => $albumId,
                'section' => $section,
                'filename' => $filename,
                'width' => $width,
                'height' => $height
            ]);
            $added++;
        }
        if (!$added && !$errors) {
            // Over post_max_size, PHP drops the whole request body
            $errors[] = 'Aucune photo reçue (trop lourdes ?)';
        }

        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return new Response(
                $added ? 200 : 422,
                ['Content-Type' => 'application/json'],
                json_encode(compact('added', 'errors'))
            );
        }
        if ($added) {
            $this->flash->success($added > 1 ? "$added photos ont été ajoutées" : 'La photo a été ajoutée');
        }
        if ($errors) {
            $this->flash->error(htmlspecialchars(join(', ', $errors)));
        }
        return $this->redirect('admin.photos.album', ['id' => $albumId]);
    }
}
