<?php

declare(strict_types=1);

namespace Lits\Command;

use Lits\Config\ScheduleConfig;
use Lits\Data\EventData;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use Safe\Exceptions\FilesystemException;

use function Latitude\QueryBuilder\field;
use function Safe\file_get_contents;

final class UpdateCommand extends DatabaseCommand
{
    /** @throws \PDOException */
    public function command(): void
    {
        $statement = $this->database->execute(
            $this->database->query
                ->select('id', 'ical')
                ->from($this->database->prefix . 'calendar')
                ->orderBy('sort', 'asc'),
        );

        $statement->fetchAll(\PDO::FETCH_FUNC, [$this, 'ical']);
    }

    /** @throws \PDOException */
    private function ical(int $id, string $ical): void
    {
        \assert($this->settings['schedule'] instanceof ScheduleConfig);

        $context = null;

        if (\is_string($this->settings['schedule']->user_agent)) {
            $context = \stream_context_create(['http' => [
                'user_agent' => $this->settings['schedule']->user_agent,
            ]]);
        }

        try {
            $contents = file_get_contents($ical, false, $context);
        } catch (FilesystemException) {
            return;
        }

        $document = Reader::read($contents);

        if (!($document instanceof VCalendar)) {
            return;
        }

        $events = $this->events($id, $document->expand(
            $this->settings['schedule']->start,
            $this->settings['schedule']->end,
            $this->settings['schedule']->timezone,
        ));

        if (\count($events) > 0) {
            $this->update($id, $events);
        }

        \sleep(10);
    }

    /** @return list<EventData> */
    private function events(int $id, VCalendar $vcalendar): array
    {
        $events = [];

        if (!isset($vcalendar->VEVENT)) {
            return $events;
        }

        foreach ($vcalendar->VEVENT as $vevent) {
            \assert($vevent instanceof VEvent);

            $events[] = EventData::fromVEvent(
                $vevent,
                $id,
                $this->settings,
                $this->database,
            );
        }

        return $events;
    }

    /**
     * @param list<EventData> $events
     * @throws \PDOException
     */
    private function update(int $id, array $events): void
    {
        \assert($this->settings['schedule'] instanceof ScheduleConfig);

        $this->database->pdo->beginTransaction();

        $this->database->execute(
            $this->database->query
                ->delete($this->database->prefix . 'event')
                ->where(field('calendar_id')->eq($id))
                ->andWhere(field('dtend')->gte(
                    $this->settings['schedule']->start->format('Y-m-d H:i:s'),
                )),
        );

        foreach ($events as $event) {
            $event->save();
        }

        $this->database->pdo->commit();
    }
}
