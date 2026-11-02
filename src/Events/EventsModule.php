<?php

namespace App\Events;

use App\Events\Actions\Ajax\AddImprovisedDancerAction;
use App\Events\Actions\Ajax\LoadDancersListAction;
use App\Events\Actions\Ajax\LoadDancersStatsAction;
use App\Events\Actions\Ajax\UpdateDancerStatusAction;
use App\Events\Actions\EventCloseAction;
use App\Events\Actions\EventCrudAction;
use App\Events\Actions\EventListAction;
use App\Events\Actions\EventListPrintAction;
use App\Events\Actions\EventOpenAction;
use App\Events\Actions\FacebookPostAction;
use App\Events\Actions\EventStatisticsAction;
use Framework\Module;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Psr\Container\ContainerInterface;

class EventsModule extends Module
{
    const DEFINITIONS = __DIR__ . '/config.php';

    const MIGRATIONS = __DIR__ . '/db/migrations';

    const SEEDS = __DIR__ . '/db/seeds';

    public function __construct(ContainerInterface $container)
    {
        $container->get(RendererInterface::class)->addPath('events', __DIR__ . '/views');
        /** @var Router $router */
        $router = $container->get(Router::class);

        if ($container->has('admin.prefix')) {
            $adminPrefix = $container->get('admin.prefix');

            $router->get(
                $adminPrefix . '/events/list/{id:[0-9]+}',
                EventListAction::class,
                'admin.events.participants'
            );

            $router->get(
                $adminPrefix . '/events/list/print/{id:[0-9]+}',
                EventListPrintAction::class,
                'admin.events.participants.print'
            );

            // POST, so CsrfMiddleware checks them: a link from another site can't close or reopen the sign-ups
            $router->post(
                $adminPrefix . '/events/close/{id:[0-9]+}',
                EventCloseAction::class,
                'admin.events.close'
            );

            $router->post(
                $adminPrefix . '/events/open/{id:[0-9]+}',
                EventOpenAction::class,
                'admin.events.open'
            );

            $router->crud(
                $adminPrefix . '/events',
                EventCrudAction::class,
                'admin.events'
            );

            $router->get(
                $adminPrefix . '/events/statistics/{id:[0-9]+}',
                EventStatisticsAction::class,
                'admin.events.statistics'
            );

            $router->post(
                $adminPrefix. '/ajax/dancer/update-status',
                UpdateDancerStatusAction::class,
                'admin.ajax.dancer.update-status'
            );

            $router->get(
                $adminPrefix.'/ajax/dancers/load-list/{id:[0-9]+}',
                LoadDancersListAction::class,
                'admin.ajax.dancers.load-list'
            );

            $router->get(
                $adminPrefix . '/ajax/dancers/get-stats/{id:[0-9]+}',
                LoadDancersStatsAction::class,
                'admin.ajax.dancers.get-stats'
            );

            $router->get(
                $adminPrefix . '/ajax/facebook-post',
                FacebookPostAction::class,
                'admin.ajax.facebook-post'
            );

            $router->post(
                $adminPrefix . '/ajax/dancers/add-improvised',
                AddImprovisedDancerAction::class,
                'admin.ajax.dancer.add-improvised'
            );
        }
    }
}
