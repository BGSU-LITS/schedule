<?php
/**
 * Phinx Configuration
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

// Vendor autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Load settings from the application
$settings = require __DIR__ . '/app/settings.php';

// Return Phinx Configuration.
return [
    'paths' => [
        'migrations' => [
            'Migrations' => '%%PHINX_CONFIG_DIR%%/migrations'
        ],
        'seeds' => [
            'Seeds' => '%%PHINX_CONFIG_DIR%%/seeds'
        ]
    ],
    'environments' => [
        'default_migration_table' => $settings['db']['prefix'] . 'phinx',
        'default_database' => 'default',
        'default' => [
            'name' => $settings['db']['name'],
            'connection' => new \PDO(
                $settings['db']['dsn'],
                $settings['db']['username'],
                $settings['db']['password']
            )
        ]
    ]
];
