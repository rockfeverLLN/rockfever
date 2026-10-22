<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Main\MainWidgets::class)
    ]),

    // Dates shown on /calendrier and in its .ics files. Add the next semester's soirées here
    // (date, week of the quadrimestre). Times are in Brussels time; an end before the start is the next day.
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
    \App\Main\Calendar::class => \DI\autowire()
        ->constructorParameter('soirees', \DI\get('calendar.soirees'))
        ->constructorParameter('start', \DI\get('calendar.start'))
        ->constructorParameter('end', \DI\get('calendar.end'))
        ->constructorParameter('place', \DI\get('calendar.place'))
        ->constructorParameter('address', \DI\get('calendar.address')),
];
