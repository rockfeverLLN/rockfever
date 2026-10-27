<?php

namespace App\Photos\Actions;

use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Saves what the photos of an album show (descriptions[photo id] => text), their text alternative for screen readers.
 * The admin's album page sends one as soon as it's written, and asks for JSON; without its script, all of them at once
 */
class PhotoDescriptionAction
{
    use RouterAwareAction;

    /**
     * Longer than a screen reader user wants to hear for a photo, and the column's size
     */
    public const MAX_LENGTH = 300;

    public function __construct(private Router $router, private PhotoTable $photoTable, private FlashService $flash)
    {
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $albumId = (int) $request->getAttribute('id');
        $descriptions = $request->getParsedBody()['descriptions'] ?? [];
        $saved = 0;
        if (is_array($descriptions)) {
            foreach ($descriptions as $photoId => $description) {
                if (!is_string($description)) {
                    continue;
                }
                // One line, without the spaces around it: empty means not described
                $description = trim(preg_replace('/\s+/u', ' ', $description));
                $description = $description === '' ? null : mb_substr($description, 0, self::MAX_LENGTH);
                $this->photoTable->describe($albumId, (int) $photoId, $description);
                $saved++;
            }
        }
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(compact('saved')));
        }
        $this->flash->success('Les descriptions ont été enregistrées');
        return $this->redirect('admin.photos.album', ['id' => $albumId]);
    }
}
