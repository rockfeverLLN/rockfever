<?php

namespace App\Photos;

use App\Main\Table\ValueTable;
use Framework\Auth;
use Framework\Database\NoRecordException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The gallery's password. Visitors who give it get a cookie for 30 days; the team, logged in, doesn't need it.
 *
 * The cookie holds an HMAC keyed with the password's hash: nobody can make one without the database, and changing
 * the password makes every cookie handed out before it useless. No session, so the browser loads the photos in
 * parallel (PHP locks the session for the length of each request)
 */
class PhotoAccess
{
    const COOKIE = 'rf_photos';

    const VALUE = 'PHOTOS_PASSWORD';

    const LIFETIME = 30 * 24 * 3600;

    /**
     * @var ValueTable
     */
    private $valueTable;

    /**
     * @var Auth
     */
    private $auth;

    private $hash;

    public function __construct(ValueTable $valueTable, Auth $auth)
    {
        $this->valueTable = $valueTable;
        $this->auth = $auth;
    }

    /**
     * Whether a password has been set, so visitors can get in at all
     */
    public function isOpen() : bool
    {
        return $this->getHash() !== '';
    }

    public function canView(ServerRequestInterface $request) : bool
    {
        $cookie = $request->getCookieParams()[self::COOKIE] ?? '';
        if ($this->isOpen() && is_string($cookie) && hash_equals($this->getToken(), $cookie)) {
            return true;
        }
        return $this->auth->getUser() !== null;
    }

    public function checkPassword(string $password) : bool
    {
        return $this->isOpen() && password_verify($password, $this->getHash());
    }

    public function setPassword(string $password) : void
    {
        $this->hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $value = $this->valueTable->findBy('name', self::VALUE);
            $this->valueTable->update($value->id, ['value' => $this->hash]);
        } catch (NoRecordException $e) {
            $this->valueTable->insert([
                'name'  => self::VALUE,
                'label' => 'Mot de passe de la galerie photos',
                'type'  => 'password',
                'value' => $this->hash
            ]);
        }
    }

    /**
     * Adds the cookie that opens the gallery to a response
     */
    public function grant(ResponseInterface $response, ServerRequestInterface $request) : ResponseInterface
    {
        if (!$this->isOpen()) {
            return $response;
        }
        $cookie = self::COOKIE . '=' . $this->getToken()
            . '; Path=/photos; Max-Age=' . self::LIFETIME . '; HttpOnly; SameSite=Lax';
        if ($request->getUri()->getScheme() === 'https') {
            $cookie .= '; Secure';
        }
        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    private function getToken() : string
    {
        return hash_hmac('sha256', 'rockfever-photos', $this->getHash());
    }

    private function getHash() : string
    {
        if ($this->hash === null) {
            try {
                $this->hash = (string) $this->valueTable->getValue(self::VALUE);
            } catch (NoRecordException $e) {
                $this->hash = '';
            }
        }
        return $this->hash;
    }
}
