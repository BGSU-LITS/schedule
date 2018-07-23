<?php
namespace Migrations;

use Phinx\Migration\AbstractMigration;

class CreateCalendarsTable extends AbstractMigration
{
    /** Changes the database for the migration. */
    public function change()
    {
        $settings = require dirname(__DIR__) . '/app/settings.php';

        $this->table($settings['db']['prefix'] . 'calendars')
            ->addColumn('title', 'string')
            ->addColumn('location', 'string')
            ->addColumn('ical', 'string')
            ->addColumn('link', 'string')
            ->addColumn('sort', 'integer')
            ->addColumn('preset', 'boolean')
            ->addIndex('sort')
            ->create();
    }
}
