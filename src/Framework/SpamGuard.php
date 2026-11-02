<?php

namespace Framework;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Keeps bots off the public forms (contact, music, sign-up):
 * - a honeypot: the field of views/honeypot.twig, hidden to people, that bots fill in like the others
 * - a limit of submissions per visitor's IP address (Throttle), so a bot that loads the form first can't send it
 *   again and again
 */
class SpamGuard
{
    const HONEYPOT = 'website';

    /**
     * @var Throttle
     */
    private $throttle;

    public function __construct(Throttle $throttle)
    {
        $this->throttle = $throttle;
    }

    /**
     * Whether a bot filled in the hidden field: answer as if the form was sent, without doing anything
     */
    public function isBot(ServerRequestInterface $request) : bool
    {
        $body = $request->getParsedBody();
        return is_array($body) && trim((string) ($body[self::HONEYPOT] ?? '')) !== '';
    }

    /**
     * Whether the visitor already sent the form $max times in the last $seconds
     */
    public function isTooMany(ServerRequestInterface $request, string $scope, int $max, int $seconds) : bool
    {
        return $this->throttle->isBlocked($request, $scope, $max, $seconds);
    }

    /**
     * Counts a form the visitor sent
     */
    public function sent(ServerRequestInterface $request, string $scope, int $seconds) : void
    {
        $this->throttle->fail($request, $scope, $seconds);
    }
}
