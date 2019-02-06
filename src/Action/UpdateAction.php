<?php
/**
 * Update Action Class
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Action;

use Aura\Sql\ExtendedPdoInterface;
use Aura\SqlQuery\QueryFactory;
use Sabre\VObject\Parser\Parser as VObject;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * An class for the update action.
 */
class UpdateAction
{
    /**
     * Extended PDO connection to a database.
     * @var ExtendedPdoInterface
     */
    private $pdo;

    /**
     * SQL query factory.
     * @var QueryFactory
     */
    private $query;

    /**
     * VObject parse.
     * @var VObject
     */
    private $vobject;

    /**
     * The prefix for tables within the database.
     * @var string
     */
    private $prefix;

    /**
     * Construct the action with objects and configuration.
     * @param ExtendedPdoInterface $pdo Extended PDO connection to a database.
     * @param QueryFactory $query SQL query factory.
     * @param VObject $vobject VObject parser.
     * @param string $prefix he prefix for tables within the database.
     */
    public function __construct(
        ExtendedPdoInterface $pdo,
        QueryFactory $query,
        VObject $vobject,
        $prefix = ''
    ) {
        $this->pdo = $pdo;
        $this->query = $query;
        $this->vobject = $vobject;
        $this->prefix = $prefix;
    }

    /**
     * Method called when class is invoked as an action.
     * @param Request $req The request for the action.
     * @param Response $res The response from the action.
     * @param array $args The arguments for the action.
     * @return Response The response from the action.
     */
    public function __invoke(Request $req, Response $res, array $args)
    {
        // Unused.
        $req;
        $args;

        // Create a timezone object from the system's timezone.
        $timezone = new \DateTimeZone(date_default_timezone_get());

        // Set the start and end time of events to load into the database.
        $start = new \DateTime('today');
        $end = new \DateTime('+6 months');

        // Select a list of all calendar IDs and ICal URLs from the database.
        $select = $this->query->newSelect()
            ->cols(['id', 'ical'])
            ->from($this->prefix . 'calendars')
            ->orderBy(['sort']);

        $calendars = $this->pdo->fetchPairs(
            $select->getStatement(),
            $select->getBindValues()
        );

        // Loop through each calendar.
        foreach ($calendars as $id => $ical) {
            // Download and parse the calendar from the ICal URL.
            $contents = $this->getContents($ical);

            if ($contents === false) {
                continue;
            }

            $vcalendar = $this->vobject->parse($contents);

            // Expand recurring events within the timerange to load.
            $vcalendar = $vcalendar->expand($start, $end, $timezone);

            // Create a new array of events by looping through calendar.
            $events = [];

            if (!empty($vcalendar->VEVENT)) {
                foreach ($vcalendar->VEVENT as $vevent) {
                    // Format the VEVENT object for insertion.
                    $event = $this->formatEvent($vevent, $timezone);

                    // Store the calendar's ID with the event.
                    $event['calendar_id'] = $id;

                    // Add the event to the array of events.
                    $events[] = $event;
                }
            }

            // Set up query to delete all events for the current calendar
            // that will be replaced by the newly downloaded events.
            $delete = $this->query->newDelete()
                ->from($this->prefix . 'events')
                ->where('calendar_id = :calendar_id')
                ->where('dtend >= :start')
                ->bindValue('calendar_id', $id)
                ->bindValue('start', $start->format('Y-m-d H:i:s'));

            // Set up query to insert all newly downloaded events if available.
            if (!empty($events)) {
                $insert = $this->query->newInsert()
                    ->into($this->prefix . 'events')
                    ->addRows($events);
            }

            // Perform both the delete and inserts within a transaction to
            // provide for highest availability of the data.
            $this->pdo->beginTransaction();

            $this->pdo->perform(
                $delete->getStatement(),
                $delete->getBindValues()
            );

            if (!empty($events)) {
                $this->pdo->perform(
                    $insert->getStatement(),
                    $insert->getBindValues()
                );
            }

            $this->pdo->commit();

            sleep(10);
        }

        return $res;
    }

    /**
     * Format a VEVENT object for insertion into the database.
     * @param \Sabre\VObject\Component\VEvent $vevent The VEVENT object.
     * @param \DateTimeZone $timezone The timezone for datetimes.
     * @return array The array of data from the VEVENT to be inserted.
     */
    private function formatEvent($vevent, $timezone)
    {
        $event = [];

        // Fields from each VEVENT object that should be treated as strings.
        $strings = [
            'summary', 'description', 'class', 'transp', 'status'
        ];

        // Store all strings to the event.
        foreach ($strings as $string) {
            $event[$string] = trim($vevent->$string);
        }

        // Fields from each VEVENT object that should be treated as datetimes.
        $datetimes = [
            'dtstart', 'dtend', 'dtstamp'
        ];

        // Store all datetimes.
        foreach ($datetimes as $datetime) {
            $event[$datetime] = $vevent->$datetime
                ->getDateTime($timezone)
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s');
        }

        return $event;
    }

    /**
     * Get contents of a URL preventing any warning from being issued.
     *
     * @param string $url The URL to retrieve.
     * @return string|bool The contents of the URL or false on error.
     */
    private function getContents($url)
    {
        set_error_handler(function () {
            // Do nothing.
        });

        $contents = file_get_contents($url);

        restore_error_handler();

        return $contents;
    }
}
