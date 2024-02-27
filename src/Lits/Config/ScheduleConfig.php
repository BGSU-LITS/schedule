<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;
use Lits\Exception\InvalidConfigException;
use Safe\DateTimeImmutable;

final class ScheduleConfig extends Config
{
    public const PATTERN_IP_LOCAL =
        '^(?:10|127|172\.(?:1[6-9]|2[0-9]|3[0-1])|192\.168)\.';

    public \DateTimeImmutable $start;
    public \DateTimeImmutable $end;
    public \DateTimeZone $timezone;
    public ?string $allow_ip = null;
    public ?string $parent_events = null;
    public ?string $parent_reservations = null;
    public ?string $user_agent = null;

    /** @throws InvalidConfigException */
    public function __construct()
    {
        try {
            $this->start = new DateTimeImmutable('now');
            $this->end = new DateTimeImmutable('+6 months');
        } catch (\Throwable $exception) {
            throw new InvalidConfigException(
                'Could not determine start or end dates',
                0,
                $exception,
            );
        }

        $this->timezone = new \DateTimeZone(\date_default_timezone_get());
    }
}
