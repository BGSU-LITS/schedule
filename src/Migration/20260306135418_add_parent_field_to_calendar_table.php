<?php

declare(strict_types=1);

namespace Migration;

use Phoenix\Exception\InvalidArgumentValueException;
use Phoenix\Migration\AbstractMigration;

final class AddParentFieldToCalendarTable extends AbstractMigration
{
    /** @throws InvalidArgumentValueException */
    protected function up(): void
    {
        $this->table('calendar')
            ->addColumn('parent', 'integer', ['null' => true])
            ->save();
    }

    protected function down(): void
    {
        $this->table('calendar')
            ->dropColumn('parent')
            ->save();
    }
}
