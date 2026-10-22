<?php
require 'www/index.php';

$migrations = [];
$seeds = [];
foreach ($app->getModules() as $module) {
    if ($module::MIGRATIONS) {
        $migrations[] = $module::MIGRATIONS;
    }
    if ($module::SEEDS) {
        $seeds[] = $module::SEEDS;
    }
}

return [
    'paths' => [
        'migrations' => $migrations,
        'seeds' => $seeds
    ],
    // Keep the 2018 Phinx defaults (signed ids) so the foreign keys in the migrations still match
    'feature_flags' => [
        'unsigned_primary_keys' => false,
        'column_null_default' => false
    ],
    'environments' => [
        'default_environment' => 'default',
        'default' => [
            'adapter' => 'mysql',
            'host' => $app->getContainer()->get('database.host'),
            'name' => $app->getContainer()->get('database.name'),
            'user' => $app->getContainer()->get('database.username'),
            'pass' => $app->getContainer()->get('database.password')
        ]
    ]
];
