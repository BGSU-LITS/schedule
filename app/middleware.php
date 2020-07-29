<?php
/**
 * Application Middleware
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

use Slim\Csrf\Guard;
use RKA\Middleware\IpAddress;
use App\Exception\AccessDeniedException;

// Add middleware for CSRF protection.
$app->add($container[Guard::class]);

if (!empty($container['settings']['app']['allow'])) {
    // Block by IP address.
    $app->add(function ($req, $res, $next) use ($container) {
        if ($ip = $req->getAttribute('ip_address')) {
            if (!preg_match($container['settings']['app']['allow'], $ip)) {
                throw new AccessDeniedException();
            }
        }

        return $next($req, $res);
    });

    // Add middleware to retrieve client IP address.
    $app->add($container[IpAddress::class]);
}
