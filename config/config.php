<?php

use Framework\Renderer\RendererInterface;
use Framework\Renderer\TwigRendererFactory;
use Framework\Router\Route;
use Framework\Router\RouterFactory;
use Framework\Router\RouterTwigExtension;
use Framework\Session\PHPSession;
use Framework\Session\SessionInterface;
use Framework\Twig\FlashExtension;
use Framework\Twig\FormExtension;
use Framework\Twig\PagerFantaExtension;
use Framework\Twig\TextExtension;
use Framework\Twig\TimeExtension;
use Framework\Twig\AssetExtension;
use Framework\Twig\CdnExtension;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;

return [
    'env'   =>  \DI\env('ENV', 'production'),
    // The Facebook Page's token, to fill an event's description from its post (App\Events\FacebookPosts). Like the
    // database credentials it's a secret: set it in config/database.php, which overrides this
    'facebook.page_token'   =>  '',
    ...require __DIR__ . '/database.php',
    'views.path'            =>   dirname(__DIR__). '/views',
    'twig.extensions'       =>   [
        \DI\get(RouterTwigExtension::class),
        \DI\get(PagerFantaExtension::class),
        \DI\get(TextExtension::class),
        \DI\get(TimeExtension::class),
        \DI\get(AssetExtension::class),
        \DI\get(CdnExtension::class),
        \DI\get(FlashExtension::class),
        \DI\get(FormExtension::class)
    ],
    SessionInterface::class => \DI\autowire(PHPSession::class),
    Route::class            => \DI\factory(RouterFactory::class),
    RendererInterface::class=> \DI\factory(TwigRendererFactory::class),
    PHPMailer::class => \DI\autowire(PHPMailer::class)->constructor(true),
    \PDO::class => function(ContainerInterface $c){
        return new \PDO(
            // Without a charset, PDO talks in the server's default (latin1 on old MySQL setups) and stores
            // accented input double-encoded: "Måneskin" becomes "MÃ¥neskin"
            'mysql:host=' . $c->get('database.host') . ';dbname=' . $c->get('database.name') . ';charset=utf8mb4',
            $c->get('database.username'),
            $c->get('database.password'),
            [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
    }
];