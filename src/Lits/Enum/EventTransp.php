<?php

declare(strict_types=1);

namespace Lits\Enum;

enum EventTransp: string
{
    case Opaque = 'OPAQUE';
    case Transparent = 'TRANSPARENT';
}
