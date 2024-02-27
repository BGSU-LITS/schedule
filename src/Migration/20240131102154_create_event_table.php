<?php

declare(strict_types=1);

namespace Migration;

use Lits\Enum\EventClass;
use Lits\Enum\EventStatus;
use Lits\Enum\EventTransp;
use Phoenix\Database\Element\ForeignKey;
use Phoenix\Exception\InvalidArgumentValueException;
use Phoenix\Migration\AbstractMigration;

final class CreateEventTable extends AbstractMigration
{
    /** @throws InvalidArgumentValueException */
    protected function up(): void
    {
        $this->table('event', false)
            ->addColumn('calendar_id', 'integer')
            ->addColumn('summary', 'string', ['length' => 255])
            ->addColumn('description', 'text')
            ->addColumn('class', 'enum', [
                'values' => \array_column(EventClass::cases(), 'value'),
                'null' => true,
            ])
            ->addColumn('status', 'enum', [
                'values' => \array_column(EventStatus::cases(), 'value'),
                'null' => true,
            ])
            ->addColumn('transp', 'enum', [
                'values' => \array_column(EventTransp::cases(), 'value'),
                'null' => true,
            ])
            ->addColumn('dtstart', 'datetime', ['null' => true])
            ->addColumn('dtend', 'datetime', ['null' => true])
            ->addColumn('dtstamp', 'datetime', ['null' => true])
            ->addForeignKey(
                'calendar_id',
                'calendar',
                'id',
                ForeignKey::CASCADE,
                ForeignKey::CASCADE,
            )
            ->addIndex(
                ['class', 'status', 'transp', 'dtstart', 'dtend', 'dtstamp'],
            )
            ->create();
    }

    protected function down(): void
    {
        $this->table('event')->drop();
    }
}
