<?php
namespace App\Events\Actions;

use App\Main\Settings;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Saves the "Réglages des soirées" of /admin/events: the usual times, place, prices and sign-up opening that every page,
 * calendar and the event form take (App\Main\Calendar, App\Main\Settings)
 */
class SoireeSettingsAction
{
    use RouterAwareAction;

    public function __construct(private Settings $settings, private Router $router, private FlashService $flash)
    {
    }

    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $errors = $this->settings->save(array_intersect_key($body, $this->settings->group('soirees')));
        if ($errors) {
            $this->flash->error(implode(' · ', $errors));
        } else {
            $this->flash->success('Les réglages des soirées ont bien été enregistrés : le site et les calendriers les utilisent déjà');
        }
        return $this->redirect('admin.events.index');
    }
}
