<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 29-Mar-18
 */

namespace App\Main\Actions;

use App\Main\Table\ValueTable;
use Framework\Actions\FormAction;
use Framework\Database\Hydrator;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\SpamGuard;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ServerRequestInterface;

class ContactAction extends FormAction
{
    /**
     * @var PHPMailer
     */
    private $mailer;

    /**
     * @var SpamGuard
     */
    private $spamGuard;

    // Messages a visitor can send in an hour
    const MAX_MESSAGES = 5;
    const MAX_MESSAGES_DELAY = 3600;

    protected $viewPath = "@main";

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        ValueTable $table,
        FlashService $flash,
        PHPMailer $mailer,
        SpamGuard $spamGuard
    ) {
        parent::__construct($renderer, $router, $table, $flash);
        $this->mailer = $mailer;
        $this->spamGuard = $spamGuard;
    }

    public function index(ServerRequestInterface $request): string
    {
        return $this->renderer->render('@main/contact');
    }

    public function edit(ServerRequestInterface $request)
    {
        if ($this->spamGuard->isBot($request)) {
            $this->flash->success("Le message a bien été envoyé !");
            return $this->redirect('contact');
        }
        $item = null;
        $errors = null;
        $validator = $this->getValidator($request);
        if ($this->spamGuard->isTooMany($request, 'contact', self::MAX_MESSAGES, self::MAX_MESSAGES_DELAY)) {
            $this->flash->error("Tu as déjà envoyé plusieurs messages : réessaie dans une heure.");
            $item = new \stdClass();
            Hydrator::hydrate($this->getParams($request), $item);
        } elseif ($validator->isValid()) {
            $this->spamGuard->sent($request, 'contact', self::MAX_MESSAGES_DELAY);
            try {
                $email = $this->table->getValue("CONTACT_EMAIL");
                $params = $this->getParams($request);

                // Sent from and to the team only: with the visitor's address in From or CC, anyone could send
                // mail through the site to any address, and servers would refuse mail from a domain that isn't ours
                $this->mailer->CharSet = 'UTF-8';
                $this->mailer->setFrom($email, 'Rock Fever');
                $this->mailer->addAddress($email, "Rock Fever");
                $this->mailer->addReplyTo($params['email'], $params['name']);
                $this->mailer->isHTML(true);
                $this->mailer->Subject = '[Rock Fever] Mail de la part de ' . $params['name'];
                $this->mailer->Body = nl2br(htmlspecialchars($params['content'])) .
                    "<br><br>--<br>Adresse de réponse : " . htmlspecialchars($params['email']);
                $this->mailer->send();
                $this->flash->success("Le message a bien été envoyé !");
                $this->flash->event('generate_lead');
            } catch (\Exception $e) {
                $this->flash->error("Le message n'a pas pu être envoyé.");
            }
        } else {
            $item = new \stdClass();
            Hydrator::hydrate($this->getParams($request), $item);
            $errors = $validator->getErrors();
        }

        return $this->renderer->render('@main/contact', compact('item', 'errors'));
    }

    protected function getParams(ServerRequestInterface $request, $item = null): array
    {
        $params = array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['name', 'email', 'content']);
        }, ARRAY_FILTER_USE_KEY);
        return $params;
    }

    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('name', 'email', 'content')
            ->notEmpty('name', 'email', 'content')
            ->length('name', null, 100)
            ->length('email', null, 254)
            ->length('content', null, 5000)
            ->email('email');
        return $validator;
    }
}
