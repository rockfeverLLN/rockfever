<?php

use App\Auth\DatabaseAuth;
use Framework\Auth;
use Framework\Auth\ForbiddenMiddleware;

return [
    'auth.login'    =>  '/login',
    Auth::class  => \DI\get(DatabaseAuth::class),
    ForbiddenMiddleware::class => \DI\autowire()
        ->constructorParameter('loginPath', \DI\get('auth.login')),
    'twig.extensions'=> \DI\add([
        \DI\get(\App\Auth\Twig\RoleExtension::class)
    ])
];
