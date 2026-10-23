<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Main\MainWidgets::class)
    ]),

    // The soirées: every page, the .ics files, the schema.org markup and /api/events take them from App\Main\Calendar,
    // which adds each one's event of the admin (same day). Add the next semester's soirées here (date, week of the
    // quadrimestre); a soirée that won't take place gets 'cancelled' => true instead of being removed.
    // Times are in Brussels time; an end before the start is the next day. The waltz time of the admin's event, when
    // there is one, is the start.
    'calendar.soirees' => [
        ['date' => '2026-09-30', 'week' => 'S3'],
        ['date' => '2026-10-14', 'week' => 'S5'],
        ['date' => '2026-11-11', 'week' => 'S9'],
        ['date' => '2026-11-18', 'week' => 'S10'],
    ],
    'calendar.start' => '20:30',
    'calendar.end' => '01:00',
    'calendar.place' => 'Hall Sainte-Barbe',
    'calendar.address' => 'Place Sainte-Barbe 1, 1348 Louvain-la-Neuve',
    // In euros, paid at the door: with a sign-up on the site, and without
    'calendar.prices' => ['online' => 3, 'door' => 4],
    \App\Main\Calendar::class => \DI\autowire()
        ->constructorParameter('soirees', \DI\get('calendar.soirees'))
        ->constructorParameter('start', \DI\get('calendar.start'))
        ->constructorParameter('end', \DI\get('calendar.end'))
        ->constructorParameter('place', \DI\get('calendar.place'))
        ->constructorParameter('address', \DI\get('calendar.address'))
        ->constructorParameter('prices', \DI\get('calendar.prices')),
];
