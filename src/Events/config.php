<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Events\EventsWidget::class)
    ]),
    'twig.extensions'   =>  \DI\add([
        \DI\get(\App\Events\Twig\EventsExtension::class)
    ])
];
