<?php

declare(strict_types=1);

namespace Lits\Action;

use Lits\Config\ScheduleConfig;
use Lits\Data\CalendarData;
use Lits\Data\EventData;
use Lits\Exception\InvalidDataException;
use Safe\DateTimeImmutable;
use Slim\Exception\HttpInternalServerErrorException;

final class EventsAction extends AuthDatabaseAction
{
    use PaginationTrait;

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        \assert($this->settings['schedule'] instanceof ScheduleConfig);
        $parent = $this->settings['schedule']->parent_events;

        try {
            $context = $this->context('now');

            if (!$this->authorize($context['iframe'], $parent)) {
                return;
            }

            $context['calendars'] = \array_filter(
                CalendarData::all($this->settings, $this->database),
                fn ($calendar) => $calendar->area !== 'Equipment',
            );

            $context['events'] = $this->events(
                $context['calendars'],
                $context['date'],
            );

            $this->render($this->template(), $context);
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                null,
                $exception,
            );
        }
    }

    /**
     * @param array<int, CalendarData> $calendars
     * @return list<EventData>
     * @throws InvalidDataException
     */
    private function events(array $calendars, string $date): array
    {
        try {
            $dtstart = new DateTimeImmutable($date);
            $dtend = $dtstart->setTime(23, 59, 59);
        } catch (\Throwable $exception) {
            throw new InvalidDataException(
                'Specified date was invalid',
                0,
                $exception,
            );
        }

        $events = [];

        foreach ($calendars as $calendar) {
            $events = \array_merge($events, EventData::withinTimespan(
                $calendar->id,
                $dtstart,
                $dtend,
                $this->settings,
                $this->database,
            ));
        }

        \usort($events, [$this, 'sort']);

        return $events;
    }

    private function sort(EventData $a, EventData $b): int
    {
        $dtstart = $a->dtstart <=> $b->dtstart;

        if ($dtstart === 0) {
            return $a->dtend <=> $b->dtend;
        }

        return $dtstart;
    }
}
