<?php
/**
 * Bookings Action Class
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2019 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Action;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use Slim\Flash\Messages;
use App\Session;
use Slim\Views\Twig;
use Aura\Sql\ExtendedPdoInterface;
use Aura\SqlQuery\QueryFactory;
use DateTimeImmutable;

/**
 * A class for the Bookings action.
 */
class BookingsAction extends IndexAction
{
    /**
     * Method called when class is invoked as an action.
     * @param Request $req The request for the action.
     * @param Response $res The response from the action.
     * @param array $args The arguments for the action.
     * @return Response The response from the action.
     */
    public function __invoke(Request $req, Response $res, array $args)
    {
        // Add flash messages to arguments.
        $args['messages'] = $this->messages();

        $args['cals'] = $req->getParam('cals');
        $args['date'] = $req->getParam('date', '');
        $args['start'] = $req->getParam('start', '');
        $args['end'] = $req->getParam('end', '');
        $args['step'] = (int) $req->getParam('step', 0);

        if (!preg_match('/^\d{4}-\d\d-\d\d$/', $args['date'])) {
            $args['date'] = date('Y-m-d');
        }

        if (!preg_match('/^\d\d:\d\d:\d\d$/', $args['start'])) {
            $args['start'] = '08:00:00';
        }

        if (!preg_match('/^\d\d:\d\d:\d\d$/', $args['end'])) {
            $args['end'] = '00:00:00';
        }

        if ($args['step'] < 1) {
            $args['step'] = 30;
        }

        if (!empty($args['cals'])) {
            if (is_array($args['cals'])) {
                sort($args['cals']);
                $args['cals'] = implode(' ', $args['cals']);

                $query = [];

                foreach (['cals', 'date', 'start', 'end', 'step'] as $key) {
                    $query[$key] = $args[$key];
                }

                return $res->withStatus(302)->withHeader(
                    'Location',
                    $req->getUri()->withQuery(
                        rawurldecode(http_build_query($query))
                    )
                );
            }

            $args['cals'] = explode(' ', $args['cals']);
        }

        $this->fetchCalendars($args);

        if (empty($args['cals']) ) {
            return $this->view->render($res, 'bookings/form.html.twig', $args);
        }

        $this->fetchEvents($args);

        // Render form template.
        return $this->view->render($res, 'bookings.html.twig', $args);
    }

    protected function fetchCalendarsDefault(&$select)
    {
    }

    /**
     * Fetches events information into the arguments.
     * @param array $args Arguments for a request.
     */
    protected function fetchEvents(&$args)
    {
        $now = new \DateTimeImmutable($args['date'] . ' ' . $args['start']);
        $end = new \DateTimeImmutable($args['date'] . ' ' . $args['end']);

        if ($end < $now) {
            $end = $end->modify('+1 day');
        }

        while ($now < $end) {
            $time = $now->format('g:i A');
            $next = $now->modify('+' . $args['step'] . ' minutes');

            foreach (array_keys($args['calendars']) as $cal) {
                $args['calendars'][$cal]['slots'][$time] = [];
            }

            $select = $this->query->newSelect()
                ->cols([
                    'calendar_id',
                    'summary',
                    'description',
                    'transp',
                    'dtstart',
                    'dtend'
                ])
                ->from($this->prefix . 'events')
                ->where('transp != "TRANSPARENT"')
                ->where('calendar_id in (:cals)')
                ->where('dtstart < :end')
                ->where('dtend > :start ')
                ->bindValue('cals', $args['cals'])
                ->bindValue('start', $now->format('Y-m-d H:i:s'))
                ->bindValue('end', $next->format('Y-m-d H:i:s'))
                ->orderBy(['dtstart', 'dtend']);

            $events = $this->pdo->fetchAll(
                $select->getStatement(),
                $select->getBindValues()
            );

            foreach ($events as $event) {
                $cal = $event['calendar_id'];

                if (empty($args['calendars'][$cal])) {
                    continue;
                }

                $preg = '/^(booked: )?(.+)\s\(?[^@]+@[^@]+\)?$/';

                if (preg_match($preg, $event['summary'], $matches)) {
                    if ($matches[2] !== 'Exchange Booking') {
                        $event['person'] = $matches[2];

                        list($firstName, $lastName) = preg_split(
                            '/\s+/',
                            $event['person']
                        );

                        if ($firstName && $lastName) {
                            $event['person'] = $firstName . ' ' .
                                substr($lastName, 0, 1) . '.';
                        }
                    }
                }

                $preg = '/^Group size: (\d+)$/m';

                if (preg_match($preg, $event['description'], $matches)) {
                    $event['size'] = $matches[1];
                }

                $args['calendars'][$cal]['slots'][$time][] = $event;
            }

            $now = $next;
        }
    }
}
