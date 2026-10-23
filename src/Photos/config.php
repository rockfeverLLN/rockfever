<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Photos\PhotosWidget::class)
    ]),
    \App\Photos\PhotographerMiddleware::class => \DI\autowire()
        ->constructorParameter('prefix', \DI\get('admin.prefix'))
];
