<?php
/**
 * Application Routes
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Action;

use Slim\Container;
use Slim\Flash\Messages;
use App\Session;
use Slim\Views\Twig;
use Aura\Sql\ExtendedPdoInterface;
use Aura\SqlQuery\QueryFactory;
use Sabre\VObject\Parser\MimeDir as VObject;

// Index action.
$container[IndexAction::class] = function (Container $container) {
    return new IndexAction(
        $container[Messages::class],
        $container[Session::class],
        $container[Twig::class],
        $container[ExtendedPdoInterface::class],
        $container[QueryFactory::class],
        $container['settings']['db']['prefix']
    );
};

$app->get('/', IndexAction::class)->setName('index');

$container[DisplayAction::class] = function (Container $container) {
    return new DisplayAction(
        $container[Messages::class],
        $container[Session::class],
        $container[Twig::class],
        $container[ExtendedPdoInterface::class],
        $container[QueryFactory::class],
        $container['settings']['db']['prefix']
    );
};

$app->get('/display', DisplayAction::class)->setName('display');

$container[EventsAction::class] = function (Container $container) {
    return new EventsAction(
        $container[Messages::class],
        $container[Session::class],
        $container[Twig::class],
        $container[ExtendedPdoInterface::class],
        $container[QueryFactory::class],
        $container['settings']['db']['prefix']
    );
};

$app->get('/events[/{page}]', EventsAction::class)->setName('events');

$container[ChangeAction::class] = function (Container $container) {
    return new ChangeAction(
        $container[Messages::class],
        $container[Session::class],
        $container[Twig::class],
        $container[ExtendedPdoInterface::class],
        $container[QueryFactory::class],
        $container['settings']['db']['prefix']
    );
};

$app->get('/change', ChangeAction::class)->setName('change');

$container[UpdateAction::class] = function (Container $container) {
    return new UpdateAction(
        $container[ExtendedPdoInterface::class],
        $container[QueryFactory::class],
        $container[VObject::class],
        $container['settings']['db']['prefix']
    );
};

$app->post('/update', UpdateAction::class);
