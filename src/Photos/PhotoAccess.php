<?php

namespace App\Photos;

use App\Photos\Entity\Album;
use Framework\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Who sees an album: everyone if it has no password, else the team, logged in, and visitors who gave its password
 * (set on its admin page). They get a cookie for that album, for 30 days.
 *
 * The cookie holds an HMAC of the album's id and password, keyed with a secret of the server: without the secret,
 * nobody can try passwords by making cookies, which the form's limit on wrong passwords wouldn't count. Changing the
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

    /**
     * @var string|null
     */
    private $secret;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Whether visitors need its password: without one, everyone sees it
     */
    public function isLocked(Album $album) : bool
    {
        return (string) $album->password !== '';
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
        if (!$this->isLocked($album)) {
            return true;
        }
        $cookie = $request->getCookieParams()[self::COOKIE . $album->id] ?? '';
        if (is_string($cookie) && hash_equals($this->getToken($album), $cookie)) {
            return true;
        }
        return $this->isTeam();
    }

    /**
     * Not case sensitive, and spaces around it don't count: it's typed on a phone in the middle of a soirée
     */
    public function checkPassword(Album $album, string $password) : bool
    {
        return $this->isLocked($album)
            && hash_equals(mb_strtolower(trim($album->password)), mb_strtolower(trim($password)));
    }

    /**
     * Adds the cookie that opens the album to a response
     */
    public function grant(ResponseInterface $response, ServerRequestInterface $request, Album $album) : ResponseInterface
    {
        if (!$this->isLocked($album)) {
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
        return hash_hmac('sha256', 'rockfever-album-' . $album->id . '|' . $album->password, $this->getSecret());
    }

    /**
     * The key of the cookies, made the first time it's needed. In storage/, which is outside the web root and which
     * deploys never touch. Without it, a new one is made and visitors give their albums' passwords again: no harm
     */
    private function getSecret() : string
    {
        if ($this->secret === null) {
            $file = dirname(__DIR__, 2) . '/storage/photo-cookies.key';
            $secret = is_file($file) ? (string) file_get_contents($file) : '';
            if (strlen($secret) !== 64) {
                if (!is_dir(dirname($file))) {
                    mkdir(dirname($file), 0755, true);
                }
                // Written aside then renamed, so another request never reads half a key
                $temporary = $file . '.' . bin2hex(random_bytes(4));
                $secret = bin2hex(random_bytes(32));
                if (file_put_contents($temporary, $secret) !== 64 || !chmod($temporary, 0600) || !rename($temporary, $file)) {
                    throw new \RuntimeException('Could not save the key of the album cookies');
                }
            }
            $this->secret = $secret;
        }
        return $this->secret;
    }
}
