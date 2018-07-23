<?php
/**
 * Index Action Class
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
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
 * An class for the index action.
 */
class IndexAction extends AbstractAction
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
     * The prefix for tables within the database.
     * @var string
     */
    private $prefix;

    /**
     * Construct the action with objects and configuration.
     * @param Messages $flash Flash messenger.
     * @param Session $session Session manager.
     * @param Twig $view View renderer.
     * @param ExtendedPdoInterface $pdo Extended PDO connection to a database.
     * @param QueryFactory $query SQL query factory.
     * @param string $prefix he prefix for tables within the database.
     */
    public function __construct(
        Messages $flash,
        Session $session,
        Twig $view,
        ExtendedPdoInterface $pdo,
        QueryFactory $query,
        $prefix = ''
    ) {
        $this->pdo = $pdo;
        $this->query = $query;
        $this->prefix = $prefix;

        parent::__construct($flash, $session, $view);
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
        // Add flash messages to arguments.
        $args['messages'] = $this->messages();

        $args['cals'] = $req->getParam('cals');
        $args['date'] = $req->getParam('date', 'today');
        $args['mode'] = $req->getParam('mode', 'slim');

        if (!empty($args['cals'])) {
            if (is_array($args['cals'])) {
                return $res->withStatus(302)->withHeader(
                    'Location',
                    $req->getUri()->withQuery(http_build_query([
                        'cals' => implode(' ', $args['cals']),
                        'date' => $args['date'],
                        'mode' => $args['mode']
                    ]))
                );
            }

            $args['cals'] = explode(' ', $args['cals']);
        }

        $this->fetchCalendars($args);
        $this->fetchBlocks($args);

        // Render form template.
        return $this->view->render($res, 'index.html.twig', $args);
    }

    /**
     * Fetches information about calendars into the arguments.
     * @param array $args Arguments for a request.
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function fetchCalendars(&$args)
    {
        // Select a list of all calendars from the database.
        $select = $this->query->newSelect()
            ->cols(['id', 'title', 'location', 'ical', 'link', 'preset'])
            ->from($this->prefix . 'calendars')
            ->orderBy(['sort']);

        if (!empty($args['cals'])) {
            $select
                ->where('id in (:cals)')
                ->bindValue('cals', $args['cals']);
        } else {
            $select->where('preset = true');
        }

        $args['calendars'] = $this->pdo->fetchAssoc(
            $select->getStatement(),
            $select->getBindValues()
        );
    }

    /**
     * Fetches information about blocks into the arguments.
     * @param array $args Arguments for a request.
     */
    private function fetchBlocks(&$args)
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
            if ($event['transp'] === 'TRANSPARENT') {
                continue;
            }

            $dtstart = new \DateTime($event['dtstart']);
            $dtend = new \DateTime($event['dtend']);

            $event['dtspan'] = $dtstart->format('F j, Y g:i A') .
                ' to ' . $dtend->format('F j, Y g:i A');

            if ($dtstart->format('Y') === $dtend->format('Y')) {
                $event['dtspan'] = $dtstart->format('l, F j g:i A') .
                    ' to ' . $dtend->format('l, F j g:i A');
            }

            if ($dtstart->format('Y-m-d') === $dtend->format('Y-m-d')) {
                $event['dtspan'] = $dtstart->format('g:i A') .
                    ' to ' . $dtend->format('g:i A');
            }

            $time = $start;

            while ($time < $end) {
                if ($dtstart <= $time && $dtend > $time) {
                    $args['blocks'][$event['calendar_id']]
                        [$time->format('H:i')][] = $event;
                }

                $time = $time->modify('+30 minutes');
            }
        }
    }
}
