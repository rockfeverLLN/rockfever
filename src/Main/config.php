<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Main\MainWidgets::class)
    ]),

    // The soirées: every page, the .ics files, the schema.org markup and /api/events take them from App\Main\Calendar,
    // which puts together each date planned in the admin (/admin/events, the soirees table) and its event (same day).
    // Their usual times, place, prices and sign-up opening are settings, edited on /admin/events (App\Main\Settings)
    \App\Main\Calendar::class => \DI\autowire(),
    \App\Main\Settings::class => \DI\autowire(),
    'twig.extensions' => \DI\add([
        \DI\get(\App\Main\SettingsExtension::class)
    ]),
];
