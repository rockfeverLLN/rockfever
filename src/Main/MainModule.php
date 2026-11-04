<?php
namespace App\Main;

use App\Main\Actions\AboutGalleryAction;
use App\Main\Actions\AccessibilityAction;
use App\Main\Actions\HomeAction;
use App\Main\Actions\AboutCrudAction;
use App\Main\Actions\AboutSectionMoveAction;
use App\Main\Actions\CalendarAction;
use App\Main\Actions\ContactAction;
use App\Main\Actions\EventAction;
use App\Main\Actions\LegalAction;
use App\Main\Actions\McpAction;
use App\Main\Actions\MusicAction;
use App\Main\Actions\SitemapAction;
use App\Main\Actions\SoireesApiAction;
use App\Main\Actions\ValuesAction;
use Framework\Module;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Psr\Container\ContainerInterface;

class MainModule extends Module
{

    const DEFINITIONS = __DIR__ . '/config.php';

    const MIGRATIONS = __DIR__ . '/db/migrations';

    const SEEDS = __DIR__ . '/db/seeds';

    public function __construct(ContainerInterface $container)
    {
        $container->get(RendererInterface::class)->addPath('main', __DIR__ . '/views');

        $router = $container->get(Router::class);

        // Home Page
        $router->get('/', HomeAction::class, 'home');
        $router->get('/home', HomeAction::class);
        $router->get('/accueil', HomeAction::class);

        // About Page
        $router->get('/event', EventAction::class, 'event');
        $router->post('/event', EventAction::class);

        // Contact Page
        $router->get('/contact', ContactAction::class, 'contact');
        $router->post('/contact', ContactAction::class);

        // Songs Page
        $router->get('/music', MusicAction::class, 'music');
        $router->post('/music', MusicAction::class);

        // CG + ROI
        $router->get('/legal', LegalAction::class, 'legal');

        // Accessibility statement
        $router->get('/accessibilite', AccessibilityAction::class, 'accessibility');

        // Dates of the soirées, and the same as .ics files for calendar apps
        $router->get('/calendrier', CalendarAction::class, 'calendar');
        $router->get('/calendrier.ics', CalendarAction::class, 'calendar.ics');
        $router->get('/calendrier/{date:\d{4}-\d{2}-\d{2}}.ics', CalendarAction::class, 'calendar.event');
        $router->get('/calendrier/{date:\d{4}-\d{2}-\d{2}}', CalendarAction::class, 'calendar.soiree');

        // The soirées as JSON, for programs and AI agents, and the pages for search engines
        $router->get('/api/events', SoireesApiAction::class, 'api.events');
        $router->get('/sitemap.xml', SitemapAction::class, 'sitemap');

        // The tools of AI assistants (Model Context Protocol). Only POST works: the others answer 405
        $router->post('/mcp', McpAction::class, 'mcp');
        $router->get('/mcp', McpAction::class);
        $router->delete('/mcp', McpAction::class);


        if ($container->has('admin.prefix')) {
            $adminPrefix = $container->get('admin.prefix');


            $router->get($adminPrefix . '/about/gallery', AboutGalleryAction::class, 'admin.about.gallery');
            $router->post($adminPrefix . '/about/gallery', AboutGalleryAction::class);

            $router->crud($adminPrefix . '/about', AboutCrudAction::class, 'admin.about');

            $router->crud($adminPrefix . '/musics', MusicsCrudAction::class, 'admin.music');

            $router->get($adminPrefix . '/values', ValuesAction::class, 'admin.values.index');
            $router->post($adminPrefix . '/values', ValuesAction::class);


            $router->post(
                $adminPrefix . '/about/moveup/{id:\d+}',
                AboutSectionMoveAction::class,
                'admin.about.moveup'
            );
            $router->post(
                $adminPrefix . '/about/movedown/{id:\d+}',
                AboutSectionMoveAction::class,
                'admin.about.movedown'
            );
        }
    }
}
