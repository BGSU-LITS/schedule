<?php

declare(strict_types=1);

namespace Lits\Data;

use Lits\Database;
use Lits\Exception\InvalidDataException;
use Lits\Settings;
use Safe\DateTimeImmutable;
use Safe\Exceptions\DatetimeException;

use function Safe\date;
use function Safe\preg_match;

final class CalendarData extends DatabaseData
{
    public int $id;
    public int $sort;
    public string $name;
    public string $area;
    public string $info;
    public string $ical;
    public string $link;
    public bool $public;
    public bool $display;
    public bool $bookings;
    public bool $landscape;

    /**
     * @param array<string, string|null> $row
     * @throws InvalidDataException
     */
    public static function fromRow(
        array $row,
        Settings $settings,
        Database $database,
    ): self {
        $calendar = new static($settings, $database);

        $data = self::findRowInt($row, 'id');

        if (\is_null($data)) {
            throw new InvalidDataException('ID must be specified');
        }

        $calendar->id = $data;
        $calendar->sort = (int) self::findRowInt($row, 'sort');
        $calendar->name = (string) self::findRowString($row, 'name');
        $calendar->area = (string) self::findRowString($row, 'area');
        $calendar->info = (string) self::findRowString($row, 'info');
        $calendar->ical = (string) self::findRowString($row, 'ical');
        $calendar->link = (string) self::findRowString($row, 'link');
        $calendar->public = self::findRowBool($row, 'public');
        $calendar->display = self::findRowBool($row, 'display');
        $calendar->bookings = self::findRowBool($row, 'bookings');
        $calendar->landscape = self::findRowBool($row, 'landscape');

        return $calendar;
    }

    /**
     * @return array<string, list<EventData>>
     * @throws InvalidDataException
     */
    public function slots(
        string $date,
        string $start,
        string $end,
        int $step,
    ): array {
        try {
            if (preg_match('/^\d{4}-\d\d-\d\d$/', $date) === 0) {
                $date = date('Y-m-d');
            }

            if (preg_match('/^\d\d:\d\d:\d\d$/', $start) === 0) {
                $start = '08:00:00';
            }

            if (preg_match('/^\d\d:\d\d:\d\d$/', $end) === 0) {
                $end = '00:00:00';
            }

            if ($step < 1) {
                $step = 30;
            }

            $start_datetime = new DateTimeImmutable($date . ' ' . $start);
            $end_datetime = new DateTimeImmutable($date . ' ' . $end);
        } catch (\Throwable $exception) {
            throw new InvalidDataException(
                'Invalid slot setup',
                0,
                $exception,
            );
        }

        return $this->compileSlots(
            $start_datetime,
            $end_datetime,
            $step,
        );
    }

    /** @return array<int, self> */
    public static function all(Settings $settings, Database $database): array
    {
        $statement = $database->execute(
            $database->query
                ->select()
                ->from('calendar')
                ->orderBy('sort', 'ASC'),
        );

        $result = [];

        /** @var array<string, string|null> $row */
        foreach ($statement as $row) {
            $object = self::fromRow($row, $settings, $database);

            $result[$object->id] = $object;
        }

        return $result;
    }

    /**
     * @return array<string, list<EventData>>
     * @throws InvalidDataException
     */
    private function compileSlots(
        DateTimeImmutable $current,
        DateTimeImmutable $end,
        int $step,
    ): array {
        $result = [];

        try {
            if ($end < $current) {
                $end = $end->modify('+1 day');
            }

            while ($current < $end) {
                $time = $current->format('g:i A');
                $next = $current->modify('+' . (string) $step . ' minutes');

                $result[$time] = EventData::withinTimespan(
                    $this->id,
                    $current,
                    $next,
                    $this->settings,
                    $this->database,
                );

                $current = $next;
            }
        } catch (DatetimeException $exception) {
            throw new InvalidDataException(
                'Could not modify timerange',
                0,
                $exception,
            );
        }

        return $result;
    }
}
