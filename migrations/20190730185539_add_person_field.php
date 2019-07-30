<?php
namespace Migrations;

use Phinx\Migration\AbstractMigration;

class AddPersonField extends AbstractMigration
{
    /** Adds the person field to the calendar table. */
    public function change()
    {
        $settings = require dirname(__DIR__) . '/app/settings.php';

        $this->table($settings['db']['prefix'] . 'calendars')
            ->addColumn('person', 'boolean')
            ->update();
    }
}
