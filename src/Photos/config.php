<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Photos\PhotosWidget::class),
        \DI\get(\App\Photos\JxlWidget::class)
    ]),
    \App\Photos\PhotographerMiddleware::class => \DI\autowire()
        ->constructorParameter('prefix', \DI\get('admin.prefix'))
];
