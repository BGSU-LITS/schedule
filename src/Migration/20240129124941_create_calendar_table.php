<?php

declare(strict_types=1);

namespace Migration;

use Phoenix\Exception\InvalidArgumentValueException;
use Phoenix\Migration\AbstractMigration;

final class CreateCalendarTable extends AbstractMigration
{
    /** @throws InvalidArgumentValueException */
    protected function up(): void
    {
        $this->table('calendar')
            ->addColumn('sort', 'integer')
            ->addColumn('name', 'string', ['length' => 255])
            ->addColumn('area', 'string', ['length' => 255])
            ->addColumn('info', 'string', ['length' => 255])
            ->addColumn('ical', 'string', ['length' => 255])
            ->addColumn('link', 'string', ['length' => 255])
            ->addColumn('public', 'boolean')
            ->addColumn('display', 'boolean')
            ->addColumn('bookings', 'boolean')
            ->addColumn('landscape', 'boolean')
            ->addIndex('sort')
            ->create();
    }

    protected function down(): void
    {
        $this->table('calendar')->drop();
    }
}
