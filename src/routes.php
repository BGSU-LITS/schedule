<?php

declare(strict_types=1);

use Lits\Action\BookingsAction;
use Lits\Action\EventsAction;
use Lits\Action\IndexAction;
use Lits\Action\ReservationsAction;
use Lits\Command\UpdateCommand;
use Lits\Framework;

return function (Framework $framework): void {
    $framework->app()
        ->get('/update', UpdateCommand::class);

    $framework->app()
        ->map(['GET', 'POST'], '/bookings', BookingsAction::class)
        ->setName('bookings');

    $framework->app()
        ->get('/events', EventsAction::class)
        ->setName('events');

    $framework->app()
        ->get('/', IndexAction::class)
        ->setName('index');

    $framework->app()
        ->get('/reservations', ReservationsAction::class)
        ->setName('reservations');
};
