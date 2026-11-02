<?php
namespace Framework\Session;

class PHPSession implements SessionInterface, \ArrayAccess
{

    /**
     * Make sure Session has been started
     */
    private function ensureStarted()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start([
                // Not readable by scripts, not sent over http, and not sent with forms posted from other sites
                'cookie_httponly' => true,
                'cookie_secure' => $this->isHttps(),
                'cookie_samesite' => 'Lax',
                // Ignores session ids the server didn't hand out
                'use_strict_mode' => true
            ]);
        }
    }

    /**
     * Whether there is a session to read: a started one, or one the browser sent the cookie of. Reading doesn't start
     * one, so a visitor's pages come without a session cookie and its no-store headers: browsers can keep them in
     * their back/forward cache. A session starts when something is saved in it (a flash message, a login)
     */
    private function exists() : bool
    {
        return session_status() == PHP_SESSION_ACTIVE || isset($_COOKIE[session_name()]);
    }

    private function isHttps() : bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function regenerate(): void
    {
        $this->ensureStarted();
        session_regenerate_id(true);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->exists()) {
            return $default;
        }
        $this->ensureStarted();
        if (array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        }
        return $default;
    }

    /**
     * @param string $key
     * @param $value
     */
    public function set(string $key, $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * @param string $key
     */
    public function delete(string $key): void
    {
        if (!$this->exists()) {
            return;
        }
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!$this->exists()) {
            return false;
        }
        $this->ensureStarted();
        return array_key_exists($offset, $_SESSION);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->delete($offset);
    }
}
