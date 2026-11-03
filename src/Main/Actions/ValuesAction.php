<?php
namespace App\Main\Actions;

use App\Main\Settings;
use Framework\Actions\RouterAwareAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Paramètres > Réglages (/admin/values): the settings of the site that aren't the soirées' (those are on /admin/events):
 * the contact address, the social links and the legal texts (App\Main\Settings)
 */
class ValuesAction
{
    use RouterAwareAction;

    public function __construct(
        private RendererInterface $renderer,
        private Router $router,
        private Settings $settings,
        private FlashService $flash
    ) {
    }

    public function __invoke(ServerRequestInterface $request)
    {
        if ($request->getMethod() === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $errors = $this->settings->save(array_intersect_key($body, $this->settings->group('site')));
            if ($errors) {
                $this->flash->error(implode(' · ', $errors));
            } else {
                $this->flash->success('Les réglages ont bien été enregistrés');
            }
            return $this->redirect('admin.values.index');
        }
        return $this->renderer->render('@main/admin/values/index', ['settings' => $this->settings->group('site')]);
    }
}
