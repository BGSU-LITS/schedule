<?php

declare(strict_types=1);

namespace Lits\Action;

use Lits\Data\CalendarData;
use Slim\Exception\HttpInternalServerErrorException;

final class BookingsAction extends DatabaseAction
{
    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        $context = $this->context($this->request->getParsedBody());

        try {
            $this->render(
                isset($context['start'])
                    ? 'action' .
                        \DIRECTORY_SEPARATOR . 'bookings' .
                        \DIRECTORY_SEPARATOR . 'print.html.twig'
                    : $this->template(),
                $context,
            );
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                null,
                $exception,
            );
        }
    }

    /**
     * @param array<mixed>|object|null $post
     * @return array<int, CalendarData>
     */
    private function calendars(array|object|null $post): array
    {
        $calendars = CalendarData::all($this->settings, $this->database);

        if (
            \is_null($post) ||
            !isset($post['calendars']) ||
            !\is_array($post['calendars'])
        ) {
            return $calendars;
        }

        return \array_filter(
            $calendars,
            fn ($key) => \in_array((string) $key, $post['calendars'], true),
            \ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param array<mixed>|object|null $post
     * @return array<string, mixed>
     */
    private function context(array|object|null $post): array
    {
        $context = ['calendars' => $this->calendars($post)];

        if (\is_null($post)) {
            return $context;
        }

        foreach (['date', 'start', 'end'] as $key) {
            if (isset($post[$key])) {
                $context[$key] = (string) $post[$key];
            }
        }

        if (isset($post['step'])) {
            $context['step'] = (int) $post['step'];
        }

        $context['landscape'] = self::postLandscape($post);

        return $context;
    }

    /**
     * @param array<mixed>|object $post
     * @return array<int>
     */
    private static function postLandscape(array|object $post): array
    {
        if (!isset($post['landscape']) || !\is_array($post['landscape'])) {
            return [];
        }

        return \array_map('intval', $post['landscape']);
    }
}
