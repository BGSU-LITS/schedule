<?php

declare(strict_types=1);

namespace Lits\Action;

use Lits\Config\AuthConfig;
use Lits\Config\ScheduleConfig;
use Safe\Exceptions\PcreException;
use Slim\Exception\HttpInternalServerErrorException;

use function Safe\preg_match;

trait PaginationTrait
{
    /** @return array{date: string, iframe: bool} */
    protected function context(string $default = 'today'): array
    {
        return [
            'date' => (string) $this->request->getQueryParam('date', $default),
            'iframe' =>
                $this->request->getQueryParam('mode') === 'iframe' ||
                $this->request->getQueryParam('iframe', false) !== false,
        ];
    }

    /** @throws HttpInternalServerErrorException */
    protected function authorize(
        bool $iframe = false,
        ?string $parent = null,
    ): bool {
        if ($this->auth->isLoggedIn()) {
            return true;
        }

        if (!$iframe) {
            \assert($this->settings['auth'] instanceof AuthConfig);

            $this->redirectLogin();

            return false;
        }

        try {
            if (!$this->authorizeIp()) {
                \assert($this->settings['schedule'] instanceof ScheduleConfig);

                $this->render('unauthorized.html.twig', [
                    'iframe' => $iframe,
                    'parent' => $parent,
                ]);

                return false;
            }
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                null,
                $exception,
            );
        }

        return true;
    }

    /** @throws PcreException */
    private function authorizeIp(): bool
    {
        \assert($this->settings['schedule'] instanceof ScheduleConfig);

        if (
            \is_null($this->settings['schedule']->allow_ip) ||
            !isset($_SERVER['REMOTE_ADDR'])
        ) {
            return false;
        }

        $ip = \filter_var(
            $_SERVER['REMOTE_ADDR'],
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_IPV4,
        );

        if (!\is_string($ip)) {
            return false;
        }

        return preg_match($this->settings['schedule']->allow_ip, $ip) !== 0;
    }
}
