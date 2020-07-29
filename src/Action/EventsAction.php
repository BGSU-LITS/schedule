<?php
/**
 * Events Action Class
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

/**
 * A class for the events action.
 */
class EventsAction extends IndexAction
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

        if (!empty($args['cals'])) {
            if (is_array($args['cals'])) {
                sort($args['cals']);

                return $res->withStatus(302)->withHeader(
                    'Location',
                    $req->getUri()->withQuery(http_build_query([
                        'cals' => implode(' ', $args['cals']),
                    ]))
                );
            }

            $args['cals'] = explode(' ', $args['cals']);
        }

        $this->fetchCalendars($args);

        $args['date'] = $req->getParam('date', 'now');
        $args['mode'] = $req->getParam('mode', 'slim');

        if (isset($args['page']) && $args['page'] === 'info') {
            return $this->view->render($res, 'events/info.html.twig', $args);
        }

        $this->fetchEvents($args);

        // Render form template.
        return $this->view->render($res, 'events.html.twig', $args);
    }

    protected function fetchCalendarsDefault(&$select)
    {
        $select->where('location != ""');
    }

    /**
     * Fetches events information into the arguments.
     * @param array $args Arguments for a request.
     */
    protected function fetchEvents(&$args)
    {
        $start = new \DateTimeImmutable($args['date']);
        $end = $start->setTime(23, 59, 59);

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
            ->where('dtstart < :end')
            ->where('dtend > :start ')
            ->bindValue('start', $start->format('Y-m-d H:i:s'))
            ->bindValue('end', $end->format('Y-m-d H:i:s'))
            ->orderBy(['dtstart', 'dtend']);

        if (!empty($args['cals'])) {
            $select
                ->where('calendar_id in (:cals)')
                ->bindValue('cals', $args['cals']);
        }

        $events = $this->pdo->fetchAll(
            $select->getStatement(),
            $select->getBindValues()
        );

        foreach ($events as $event) {
            if (empty($args['calendars'][$event['calendar_id']])) {
                continue;
            }

            $dtstart = new \DateTime($event['dtstart']);
            $dtend = new \DateTime($event['dtend']);

            $timestart = str_replace(':00', '', $dtstart->format('g:i A'));
            $timeend = str_replace(':00', '', $dtend->format('g:i A'));

            $event['dtspan'] = $dtstart->format('F j, Y ') . $timestart .
                ' to ' . $dtend->format('F j, Y ') . $timeend;

            if ($dtstart->format('Y') === $dtend->format('Y')) {
                $event['dtspan'] = $dtstart->format('l, F j ') . $timestart .
                    ' to ' . $dtend->format('l, F j ') . $timeend;
            }

            if ($dtstart->format('Y-m-d') === $dtend->format('Y-m-d')) {
                $event['dtspan'] = $timestart . ' to ' . $timeend;
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

            $args['events'][] = $event;
        }
    }
}
