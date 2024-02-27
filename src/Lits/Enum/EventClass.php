<?php

declare(strict_types=1);

namespace Lits\Enum;

enum EventClass: string
{
    case Private = 'PRIVATE';
    case Public = 'PUBLIC';
}
