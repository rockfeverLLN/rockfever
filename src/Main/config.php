<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Main\MainWidgets::class)
    ]),

    // The soirées: every page, the .ics files, the schema.org markup and /api/events take them from App\Main\Calendar,
    // which puts together each date planned in the admin (/admin/events, the soirees table) and its event (same day).
    // Times are in Brussels time; an end before the start is the next day. Every soirée has the usual waltz (its start)
    // and rock times, unless its event in the admin gives others.
    'calendar.start' => '20:30',
    'calendar.rock' => '21:00',
    'calendar.end' => '01:00',
    'calendar.place' => 'Hall Sainte-Barbe',
    'calendar.address' => 'Place Sainte-Barbe 1, 1348 Louvain-la-Neuve',
    // In euros, paid at the door: with a sign-up on the site, and without
    'calendar.prices' => ['online' => 3, 'door' => 4],
    \App\Main\Calendar::class => \DI\autowire()
        ->constructorParameter('start', \DI\get('calendar.start'))
        ->constructorParameter('rock', \DI\get('calendar.rock'))
        ->constructorParameter('end', \DI\get('calendar.end'))
        ->constructorParameter('place', \DI\get('calendar.place'))
        ->constructorParameter('address', \DI\get('calendar.address'))
        ->constructorParameter('prices', \DI\get('calendar.prices')),
];
