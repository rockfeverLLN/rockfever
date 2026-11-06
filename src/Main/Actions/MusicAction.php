<?php
/**.
 * User: Nicolas van de Walle
 * Date: 21-Aug-18
 * Time: 20:39
 */

namespace App\Main\Actions;

use App\Main\Table\MusicTable;
use Framework\Actions\FormAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\SpamGuard;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ServerRequestInterface;

class MusicAction extends FormAction
{
    protected $viewPath = '@main';

    // Songs a visitor can suggest in an hour
    const MAX_SUGGESTIONS = 10;
    const MAX_SUGGESTIONS_DELAY = 3600;

    /**
     * @var SpamGuard
     */
    private $spamGuard;

    protected $messages = [
        'create'=>  "Merci pour ta suggestion de musique !"
    ];

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        MusicTable $musicTable,
        FlashService $flash,
        SpamGuard $spamGuard
    ) {
        parent::__construct($renderer, $router, $musicTable, $flash);
        $this->spamGuard = $spamGuard;
    }

    public function index(Request $request): string
    {
        return $this->renderer->render($this->viewPath . '/music');
    }

    public function edit(ServerRequestInterface $request)
    {
        if ($this->spamGuard->isBot($request)) {
            $this->flash->success($this->messages['create']);
            return $this->redirect('music');
        }
        $validator = $this->getValidator($request);
        if ($this->spamGuard->isTooMany($request, 'music', self::MAX_SUGGESTIONS, self::MAX_SUGGESTIONS_DELAY)) {
            $this->flash->error("Tu as déjà proposé beaucoup de musiques : réessaie dans une heure.");
        } elseif ($validator->isValid()) {
            $this->spamGuard->sent($request, 'music', self::MAX_SUGGESTIONS_DELAY);
            $params = $this->getParams($request);
            $this->table->insert($params);
            $this->flash->success($this->messages['create']);
            return $this->redirect('music');
        } else {
            $this->flash->error("Merci de bien vouloir remplir le formulaire");
        }

        return $this->renderer->render($this->viewPath . '/music');
    }

    protected function getParams(ServerRequestInterface $request, $item = null): array
    {
        $params = array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['title','artist']);
        }, ARRAY_FILTER_USE_KEY);
        return $params;
    }

    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('title', 'artist')
            ->notEmpty('title')
            ->length('title', null, 255)
            ->length('artist', null, 255);
        return $validator;
    }
}
