<?php
namespace Migrations;

use Phinx\Migration\AbstractMigration;

class CreateEventsTable extends AbstractMigration
{
    /** Changes the database for the migration. */
    public function change()
    {
        $settings = require dirname(__DIR__) . '/app/settings.php';

        $this->table($settings['db']['prefix'] . 'events', ['id' => false])
            ->addColumn('calendar_id', 'integer')
            ->addColumn('summary', 'string')
            ->addColumn('description', 'string')
            ->addColumn('class', 'string')
            ->addColumn('transp', 'string')
            ->addColumn('status', 'string')
            ->addColumn('dtstart', 'datetime')
            ->addColumn('dtend', 'datetime')
            ->addColumn('dtstamp', 'datetime')
            ->addIndex([
                'class', 'transp', 'status', 'dtstart', 'dtend', 'dtstamp'
            ])
            ->addForeignKey(
                'calendar_id',
                $settings['db']['prefix'] . 'calendars',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE']
            )
            ->create();
    }
}
