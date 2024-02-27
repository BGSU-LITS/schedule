<?php

declare(strict_types=1);

namespace Lits\Action;

use Lits\Config\ScheduleConfig;
use Lits\Data\CalendarData;
use Lits\Data\EventData;
use Lits\Exception\InvalidDataException;
use Safe\DateTimeImmutable;
use Slim\Exception\HttpInternalServerErrorException;

final class ReservationsAction extends AuthDatabaseAction
{
    use PaginationTrait;

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        \assert($this->settings['schedule'] instanceof ScheduleConfig);
        $parent = $this->settings['schedule']->parent_reservations;

        try {
            $context = $this->context();

            if (!$this->authorize($context['iframe'], $parent)) {
                return;
            }

            $context['calendars'] = \array_filter(
                CalendarData::all($this->settings, $this->database),
                fn ($calendar) => $calendar->display,
            );

            $context['blocks'] = $this->blocks(
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
     * @return array<int, array<string, list<EventData>>>
     * @throws InvalidDataException
     */
    private function blocks(array $calendars, string $date): array
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

        $blocks = [];

        foreach ($calendars as $calendar) {
            $blocks[$calendar->id] = [];

            $events = EventData::withinTimespan(
                $calendar->id,
                $dtstart,
                $dtend,
                $this->settings,
                $this->database,
            );

            foreach ($events as $event) {
                $blocks[$calendar->id] = \array_merge_recursive(
                    $blocks[$calendar->id],
                    \array_fill_keys(
                        $event->blocks($dtstart, $dtend),
                        [$event],
                    ),
                );
            }
        }

        return $blocks;
    }
}
