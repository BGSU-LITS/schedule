<?php
/**
 * Access Denied Exception Class
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Exception;

/**
 * An exception for errors when submitting a request.
 */
class AccessDeniedException extends AbstractException
{
    public $title = 'Access Denied';
    public $message = 'This resource is only available from certain networks.';
}
