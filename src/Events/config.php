<?php
return [
    \App\Events\FacebookPosts::class => \DI\autowire()->constructor(\DI\get('facebook.page_token')),
    'admin.widgets' => \DI\add([
        \DI\get(\App\Events\EventsWidget::class)
    ]),
    'twig.extensions'   =>  \DI\add([
        \DI\get(\App\Events\Twig\EventsExtension::class)
    ])
];
