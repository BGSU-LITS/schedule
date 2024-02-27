<?php

declare(strict_types=1);

namespace Lits\Data;

use DateTimeInterface as DateTime;
use Lits\Config\ScheduleConfig;
use Lits\Database;
use Lits\Enum\EventClass;
use Lits\Enum\EventStatus;
use Lits\Enum\EventTransp;
use Lits\Exception\InvalidDataException;
use Lits\Settings;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property\ICalendar\DateTime as DateTimeProperty;
use Safe\DateTimeImmutable;

use function Latitude\QueryBuilder\field;
use function Safe\preg_match;
use function Safe\preg_split;

final class EventData extends DatabaseData
{
    public string $summary = '';
    public string $description = '';
    public ?EventClass $class = null;
    public ?EventStatus $status = null;
    public ?EventTransp $transp = null;
    public ?DateTime $dtstart = null;
    public ?DateTime $dtend = null;
    public ?DateTime $dtstamp = null;

    public function __construct(
        public int $calendar_id,
        Settings $settings,
        Database $database,
    ) {
        parent::__construct($settings, $database);
    }

    /**
     * @param array<string, string|null> $row
     * @throws InvalidDataException
     */
    public static function fromRow(
        array $row,
        Settings $settings,
        Database $database,
    ): self {
        $calendar_id = self::findRowInt($row, 'calendar_id');

        if (\is_null($calendar_id)) {
            throw new InvalidDataException('Calendar ID must be specified');
        }

        $event = new self($calendar_id, $settings, $database);
        $event->summary = (string) self::findRowString($row, 'summary');
        $event->description =
            (string) self::findRowString($row, 'description');

        if (isset($row['class'])) {
            $event->class = EventClass::tryFrom($row['class']);
        }

        if (isset($row['status'])) {
            $event->status = EventStatus::tryFrom($row['status']);
        }

        if (isset($row['transp'])) {
            $event->transp = EventTransp::tryFrom($row['transp']);
        }

        $event->dtstart = self::findRowDatetime($row, 'dtstart');
        $event->dtend = self::findRowDatetime($row, 'dtend');
        $event->dtstamp = self::findRowDatetime($row, 'dtstamp');

        return $event;
    }

    public static function fromVEvent(
        VEvent $vevent,
        int $calendar_id,
        Settings $settings,
        Database $database,
    ): self {
        $event = new self($calendar_id, $settings, $database);

        if (isset($vevent->summary)) {
            $event->summary = \trim((string) $vevent->summary);
        }

        if (isset($vevent->description)) {
            $event->description = \trim((string) $vevent->description);
        }

        if (isset($vevent->class)) {
            $event->class = EventClass::tryFrom((string) $vevent->class);
        }

        if (isset($vevent->status)) {
            $event->status = EventStatus::tryFrom((string) $vevent->status);
        }

        if (isset($vevent->transp)) {
            $event->transp = EventTransp::tryFrom((string) $vevent->transp);
        }

        $event->loadVEventDates($vevent);

        return $event;
    }

    /** @return list<string> */
    public function blocks(DateTime $dtstart, DateTime $dtend): array
    {
        $blocks = [];

        if (\is_null($this->dtstart)) {
            return $blocks;
        }

        $current = DateTimeImmutable::createFromInterface($this->dtstart);

        while ($current < $this->dtend) {
            if ($current >= $dtstart && $current < $dtend) {
                $blocks[] = $current->format('H:i');
            }

            $current = $current->modify('+30 minutes');
        }

        return $blocks;
    }

    public function dtspan(): ?string
    {
        if (\is_null($this->dtstart) || \is_null($this->dtend)) {
            return null;
        }

        if ($this->dtstart->format('Ymd') === $this->dtend->format('Ymd')) {
            return $this->dtstart->format('g:i A') .
                ' to ' . $this->dtend->format('g:i A');
        }

        if ($this->dtstart->format('Y') === $this->dtend->format('Y')) {
            return $this->dtstart->format('l, F j g:i A') .
                ' to ' . $this->dtend->format('l, F j g:i A');
        }

        return $this->dtstart->format('F j, Y g:i A') .
            ' to ' . $this->dtend->format('F j, Y g:i A');
    }

    /** @throws InvalidDataException */
    public function person(bool $public = false, bool $email = false): ?string
    {
        try {
            preg_match(
                '/^(booked: )?([^[]+)(\s\[.+\])?(\s\([^@]+@[^@)]+\))$/',
                $this->summary,
                $matches,
            );
        } catch (\Throwable $exception) {
            throw new InvalidDataException(
                'Could not parse person from summary',
                0,
                $exception,
            );
        }

        if (!isset($matches[2]) || $matches[2] === 'Exchange Booking') {
            return null;
        }

        return self::parseName($matches[2], $public) .
            ($email && isset($matches[4]) ? $matches[4] : '');
    }

    /** @throws InvalidDataException */
    public function size(): ?int
    {
        try {
            preg_match('/^Group size: (\d+)$/m', $this->description, $matches);
        } catch (\Throwable $exception) {
            throw new InvalidDataException(
                'Could not parse size from description',
                0,
                $exception,
            );
        }

        if (!isset($matches[1])) {
            return null;
        }

        return (int) $matches[1];
    }

    /** @throws \PDOException */
    public function save(): void
    {
        $this->database->insertIgnore('event', [
            'calendar_id' => $this->calendar_id,
            'summary' => $this->summary,
            'description' => $this->description,
            'class' => self::prepareEnum($this->class),
            'status' => self::prepareEnum($this->status),
            'transp' => self::prepareEnum($this->transp),
            'dtstart' => self::prepareDateTime($this->dtstart),
            'dtend' => self::prepareDateTime($this->dtend),
            'dtstamp' => self::prepareDateTime($this->dtstamp),
        ]);
    }

    /** @return list<self> */
    public static function withinTimespan(
        int $calendar_id,
        DateTime $start,
        DateTime $end,
        Settings $settings,
        Database $database,
    ): array {
        $statement = $database->execute(
            $database->query
                ->select()
                ->from('event')
                ->where(field('transp')->notEq('TRANSPARENT'))
                ->andWhere(field('calendar_id')->eq($calendar_id))
                ->andWhere(field('dtstart')->lt($end->format('Y-m-d H:i:s')))
                ->andWhere(field('dtend')->gt($start->format('Y-m-d H:i:s')))
                ->orderBy('dtstart')
                ->orderBy('dtend'),
        );

        $result = [];

        /** @var array<string, string|null> $row */
        foreach ($statement as $row) {
            $object = self::fromRow($row, $settings, $database);

            $result[] = $object;
        }

        return $result;
    }

    private function loadVEventDates(VEvent $vevent): void
    {
        \assert($this->settings['schedule'] instanceof ScheduleConfig);

        if (isset($vevent->dtstart)) {
            \assert($vevent->dtstart instanceof DateTimeProperty);

            $this->dtstart = $vevent->dtstart
                ->getDateTime($this->settings['schedule']->timezone)
                ->setTimezone($this->settings['schedule']->timezone);
        }

        if (isset($vevent->dtend)) {
            \assert($vevent->dtend instanceof DateTimeProperty);

            $this->dtend = $vevent->dtend
                ->getDateTime($this->settings['schedule']->timezone)
                ->setTimezone($this->settings['schedule']->timezone);
        }

        if (!isset($vevent->dtstamp)) {
            return;
        }

        \assert($vevent->dtstamp instanceof DateTimeProperty);

        $this->dtstamp = $vevent->dtstamp
            ->getDateTime($this->settings['schedule']->timezone)
            ->setTimezone($this->settings['schedule']->timezone);
    }

    /** @throws InvalidDataException */
    private static function parseName(
        string $name,
        bool $public = false,
    ): string {
        $prefixes = '/^((Dr|Mrs?|Ms|Mx)\.|Miss)$/';

        try {
            [$first, $last] = preg_split('/\s+/', $name, 2);
            \assert(\is_string($first) && \is_string($last));

            if ($last === '') {
                return $first;
            }

            if ($public && preg_match($prefixes, $first) === 0) {
                return $first . ' ' . \substr($last, 0, 1) . '.';
            }

            return $first . ' ' . $last;
        } catch (\Throwable $exception) {
            throw new InvalidDataException(
                'Could not parse name',
                0,
                $exception,
            );
        }
    }

    private static function prepareEnum(?\BackedEnum $enum): string|int|null
    {
        return \is_null($enum) ? null : $enum->value;
    }

    private static function prepareDateTime(?DateTime $datetime): ?string
    {
        return \is_null($datetime) ? null : $datetime->format('Y-m-d H:i:s');
    }
}
