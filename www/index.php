<?php
use App\Admin\AdminModule;
use App\Auth\AdminOnlyMiddleware;
use App\Auth\AuthModule;
use App\Events\EventsModule;
use App\Events\Middleware\NextEventMiddleware;
use App\Main\MainModule;
use Framework\App;
use Framework\Auth\ForbiddenMiddleware;
use Framework\Auth\LoggedInMiddleWare;
use Framework\Middleware\CsrfMiddleware;
use Framework\Middleware\DispatcherMiddleware;
use Framework\Middleware\ExceptionCatcherMiddleware;
use Framework\Middleware\MethodMiddleware;
use Framework\Middleware\NotFoundMiddleware;
use Framework\Middleware\RouterMiddleware;
use Framework\Middleware\TrailingSlashMiddleWare;
use GuzzleHttp\Psr7\ServerRequest;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Middlewares\Whoops;

chdir(dirname(__DIR__));

require "vendor/autoload.php";

$app = (new App('config/config.php'))
    ->addModule(AdminModule::class)
    ->addModule(AuthModule::class)
    ->addModule(EventsModule::class)
    ->addModule(MainModule::class);


$container = $app->getContainer();
$env = $container->get('env');

if ($env == 'dev') {
    $app->pipe(Whoops::class);
} else {
    $app->pipe(ExceptionCatcherMiddleware::class);
}

$app->pipe(TrailingSlashMiddleWare::class)
    ->pipe(ForbiddenMiddleware::class)
    ->pipe('/event', NextEventMiddleware::class)
    ->pipe($container->get('admin.prefix'), LoggedInMiddleWare::class)
    ->pipe($container->get('admin.prefix').'/about', AdminOnlyMiddleware::class)
    ->pipe($container->get('admin.prefix').'/roles', AdminOnlyMiddleware::class)
    ->pipe($container->get('admin.prefix').'/values', AdminOnlyMiddleware::class)
    ->pipe(MethodMiddleware::class)
    //->pipe(CsrfMiddleware::class) // Due to some Chrome mobile issues
    ->pipe(RouterMiddleware::class)
    ->pipe(DispatcherMiddleware::class)
    ->pipe(NotFoundMiddleware::class)
;

if (php_sapi_name() !== "cli") {
    $response = $app->run(ServerRequest::fromGlobals());
    (new SapiEmitter())->emit($response);
}
