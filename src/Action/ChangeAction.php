<?php
/**
 * Change Action Class
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
 * An class for the change action.
 */
class ChangeAction extends AbstractAction
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
        // Unused.
        $req;

        // Add flash messages to arguments.
        $args['messages'] = $this->messages();

        // Select a list of all calendars from the database.
        $select = $this->query->newSelect()
            ->cols(['id', 'title', 'location', 'ical', 'link', 'preset'])
            ->from($this->prefix . 'calendars')
            ->orderBy(['sort']);

        $args['calendars'] = $this->pdo->fetchAssoc(
            $select->getStatement(),
            $select->getBindValues()
        );

        // Render form template.
        return $this->view->render($res, 'change.html.twig', $args);
    }
}
