<?php

use App\Admin\Actions\DashboardAction;
use App\Admin\AdminModule;
use App\Admin\AdminTwigExtension;

return [
    'admin.prefix'  =>  '/admin',
    'admin.widgets' =>  [],
    AdminTwigExtension::class => \DI\autowire()->constructor(\DI\get('admin.widgets')),
    AdminModule::class => \DI\autowire()->constructorParameter(
        'prefix',
        \DI\get('admin.prefix')
    ),
    DashboardAction::class => \DI\autowire()->constructorParameter(
        'widgets',
        \DI\get('admin.widgets')
    )
];
