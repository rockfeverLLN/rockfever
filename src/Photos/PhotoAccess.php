<?php

namespace App\Photos;

use App\Photos\Entity\Album;
use Framework\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Who sees an album: the team, logged in, and visitors who gave the photo password of the album's soirée (set on
 * the event in the admin). They get a cookie for that album, for 30 days. An album whose soirée has no password
 * is only for the team.
 *
 * The cookie holds an HMAC keyed with the password: nobody can make one without knowing it, and changing the
 * password makes the cookies handed out before useless. No session, so the browser loads the photos in parallel
 * (PHP locks the session for the length of each request)
 */
class PhotoAccess
{
    const COOKIE = 'rf_album_';

    const LIFETIME = 30 * 24 * 3600;

    /**
     * @var Auth
     */
    private $auth;

    private $team;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Whether visitors can get into the album at all
     */
    public function isOpen(Album $album) : bool
    {
        return (string) $album->photosPassword !== '';
    }

    public function isTeam() : bool
    {
        if ($this->team === null) {
            $this->team = $this->auth->getUser() !== null;
        }
        return $this->team;
    }

    public function canView(ServerRequestInterface $request, Album $album) : bool
    {
        $cookie = $request->getCookieParams()[self::COOKIE . $album->id] ?? '';
        if ($this->isOpen($album) && is_string($cookie) && hash_equals($this->getToken($album), $cookie)) {
            return true;
        }
        return $this->isTeam();
    }

    /**
     * Not case sensitive, and spaces around it don't count: it's typed on a phone in the middle of a soirée
     */
    public function checkPassword(Album $album, string $password) : bool
    {
        return $this->isOpen($album)
            && hash_equals(mb_strtolower(trim($album->photosPassword)), mb_strtolower(trim($password)));
    }

    /**
     * Adds the cookie that opens the album to a response
     */
    public function grant(ResponseInterface $response, ServerRequestInterface $request, Album $album) : ResponseInterface
    {
        if (!$this->isOpen($album)) {
            return $response;
        }
        $cookie = self::COOKIE . $album->id . '=' . $this->getToken($album)
            . '; Path=/photos; Max-Age=' . self::LIFETIME . '; HttpOnly; SameSite=Lax';
        if ($request->getUri()->getScheme() === 'https') {
            $cookie .= '; Secure';
        }
        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    private function getToken(Album $album) : string
    {
        return hash_hmac('sha256', 'rockfever-album-' . $album->id, $album->photosPassword);
    }
}
