<?php

namespace App\Photos\Actions;

use App\Main\Table\ValueTable;
use App\Photos\PhotoAccess;
use App\Photos\Table\AlbumTable;
use App\Photos\Table\DeletionRequestTable;
use App\Photos\Table\PhotoTable;
use Framework\Actions\RouterAwareAction;
use Framework\Database\NoRecordException;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\Validator;
use GuzzleHttp\Psr7\Response;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Someone on a photo asks for it to be taken down. The team sees the request on /admin/photos and gets an email
 */
class DeletionRequestAction
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
     * @var DeletionRequestTable
     */
    private $requestTable;

    /**
     * @var PhotoAccess
     */
    private $access;

    /**
     * @var FlashService
     */
    private $flash;

    /**
     * @var ValueTable
     */
    private $valueTable;

    /**
     * @var PHPMailer
     */
    private $mailer;

    public function __construct(
        Router $router,
        PhotoTable $photoTable,
        AlbumTable $albumTable,
        DeletionRequestTable $requestTable,
        PhotoAccess $access,
        FlashService $flash,
        ValueTable $valueTable,
        PHPMailer $mailer
    ) {
        $this->router = $router;
        $this->photoTable = $photoTable;
        $this->albumTable = $albumTable;
        $this->requestTable = $requestTable;
        $this->access = $access;
        $this->flash = $flash;
        $this->valueTable = $valueTable;
        $this->mailer = $mailer;
    }

    public function __invoke(Request $request) : ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        try {
            $album = $this->albumTable->findForAccess((int) $this->photoTable->find($id)->albumId);
        } catch (NoRecordException $e) {
            $album = null;
        }
        if (!$album || !$this->access->canView($request, $album)) {
            return $this->answer($request, false, 'Cette photo n\'est plus disponible.', 404);
        }
        $params = array_map(
            fn ($value) => is_string($value) ? trim($value) : '',
            array_intersect_key($request->getParsedBody() ?? [], array_flip(['name', 'email', 'reason']))
        ) + ['name' => '', 'email' => '', 'reason' => ''];
        $validator = (new Validator($params))
            ->notEmpty('name')
            ->length('name', null, 100)
            ->length('email', null, 255)
            ->length('reason', null, 2000);
        if (!$validator->isValid() || ($params['email'] !== '' && !filter_var($params['email'], FILTER_VALIDATE_EMAIL))) {
            return $this->answer($request, false, 'Indique ton nom (et une adresse email valide si tu en donnes une).', 422);
        }
        $this->requestTable->insert([
            'photo_id' => $id,
            'name' => $params['name'],
            'email' => $params['email'] ?: null,
            'reason' => $params['reason'] ?: null
        ]);
        $this->notify($request, $params);
        return $this->answer(
            $request,
            true,
            'Merci, ta demande a bien été envoyée. L\'équipe retirera la photo au plus vite.',
            200,
            $album->slug
        );
    }

    /**
     * JSON for the gallery's script, a redirection with a message without it
     */
    private function answer(
        Request $request,
        bool $ok,
        string $message,
        int $status = 200,
        ?string $album = null
    ) : ResponseInterface
    {
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode(compact('ok', 'message'))
            );
        }
        $ok ? $this->flash->success($message) : $this->flash->error($message);
        return $album ? $this->redirect('photos.album', ['slug' => $album]) : $this->redirect('photos');
    }

    /**
     * Emails the team (CONTACT_EMAIL, as the contact form). The request is saved even if that fails
     */
    private function notify(Request $request, array $params) : void
    {
        try {
            $email = $this->valueTable->getValue('CONTACT_EMAIL');
            $uri = $request->getUri();
            $link = $uri->getScheme() . '://' . $uri->getAuthority() . $this->router->generateUri('admin.photos.index');
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom($email, 'Rock Fever');
            $this->mailer->addAddress($email, 'Rock Fever');
            if ($params['email'] !== '') {
                $this->mailer->addReplyTo($params['email'], $params['name']);
            }
            $this->mailer->isHTML(true);
            $this->mailer->Subject = '[Rock Fever] Demande de suppression d\'une photo';
            $this->mailer->Body = '<p>' . htmlspecialchars($params['name']) . ' demande qu\'une photo de la galerie soit retirée.</p>'
                . ($params['reason'] !== '' ? '<p>' . nl2br(htmlspecialchars($params['reason'])) . '</p>' : '')
                . ($params['email'] !== '' ? '<p>Adresse de réponse : ' . htmlspecialchars($params['email']) . '</p>' : '')
                . '<p><a href="' . htmlspecialchars($link) . '">Voir la demande et la photo</a></p>';
            $this->mailer->send();
        } catch (\Throwable $e) {
            // The request is on /admin/photos anyway
        }
    }
}
