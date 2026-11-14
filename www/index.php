<?php
use App\Admin\AdminModule;
use App\Auth\AdminOnlyMiddleware;
use App\Auth\AuthModule;
use App\Events\EventsModule;
use App\Events\Middleware\NextEventMiddleware;
use App\Main\MainModule;
use App\Photos\PhotographerMiddleware;
use App\Photos\PhotosModule;
use Framework\App;
use Framework\Auth\ForbiddenMiddleware;
use Framework\Auth\LoggedInMiddleWare;
use Framework\Middleware\ContentSecurityPolicyMiddleware;
use Framework\Middleware\CsrfMiddleware;
use Framework\Middleware\DispatcherMiddleware;
use Framework\Middleware\ExceptionCatcherMiddleware;
use Framework\Middleware\MarkdownMiddleware;
use Framework\Middleware\MethodMiddleware;
use Framework\Middleware\NotFoundMiddleware;
use Framework\Middleware\RouterMiddleware;
use Framework\Middleware\TrailingSlashMiddleWare;
use GuzzleHttp\Psr7\ServerRequest;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Middlewares\Whoops;

chdir(dirname(__DIR__));

// From __DIR__, not the current folder: on OVH, PHP sometimes can't resolve the current folder (getcwd() fails),
// which left every __DIR__ below relative and Twig looking for its templates at /./views (the outage of 2026-11-14)
require dirname(__DIR__) . "/vendor/autoload.php";

$app = (new App(dirname(__DIR__) . '/config/config.php'))
    ->addModule(AdminModule::class)
    ->addModule(AuthModule::class)
    ->addModule(EventsModule::class)
    ->addModule(MainModule::class)
    ->addModule(PhotosModule::class);


$container = $app->getContainer();
$env = $container->get('env');

// First, so the error pages get the nonce too
$app->pipe(ContentSecurityPolicyMiddleware::class);
if ($env == 'dev') {
    $app->pipe(Whoops::class);
} else {
    $app->pipe(ExceptionCatcherMiddleware::class);
}

$app->pipe(MarkdownMiddleware::class)
    ->pipe(TrailingSlashMiddleWare::class)
    ->pipe(ForbiddenMiddleware::class)
    ->pipe('/event', NextEventMiddleware::class)
    ->pipe($container->get('admin.prefix'), LoggedInMiddleWare::class)
    ->pipe($container->get('admin.prefix'), PhotographerMiddleware::class)
    ->pipe($container->get('admin.prefix').'/about', AdminOnlyMiddleware::class)
    ->pipe($container->get('admin.prefix').'/roles', AdminOnlyMiddleware::class)
    ->pipe($container->get('admin.prefix').'/values', AdminOnlyMiddleware::class)
    ->pipe($container->get('admin.prefix').'/events/settings', AdminOnlyMiddleware::class)
    ->pipe(MethodMiddleware::class)
    ->pipe(CsrfMiddleware::class)
    ->pipe(RouterMiddleware::class)
    ->pipe(DispatcherMiddleware::class)
    ->pipe(NotFoundMiddleware::class)
;

if (php_sapi_name() !== "cli") {
    $response = $app->run(ServerRequest::fromGlobals());
    (new SapiEmitter())->emit($response);
}
