<?php
/**.
 * User: Nicolas van de Walle
 * Date: 15-Feb-18
 * Time: 14:21
 */

namespace Framework\Session;

class FlashService
{

    /**
     * @var SessionInterface
     */
    private $session;

    private $sessionKey = 'flash';

    private $messages;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function success(string $message)
    {
        $this->session->get($this->sessionKey, []);
        $flash['success'] = $message;
        $this->session->set($this->sessionKey, $flash);
    }

    public function error(string $message)
    {
        $this->session->get($this->sessionKey, []);
        $flash['error'] = $message;
        $this->session->set($this->sessionKey, $flash);
    }

    /**
     * A Google Analytics event (a GA4 event name) for the next page to send, so it's counted after a redirect too.
     * Call it after success(), which replaces the whole flash
     */
    public function event(string $name)
    {
        $flash = $this->session->get($this->sessionKey, []);
        $flash['event'] = $name;
        $this->session->set($this->sessionKey, $flash);
    }

    public function get(string $type) : ?string
    {
        if (is_null($this->messages)) {
            $this->messages = $this->session->get($this->sessionKey, []);
            $this->session->delete($this->sessionKey);
        }
        if (array_key_exists($type, $this->messages)) {
            return $this->messages[$type];
        }
        return null;
    }
}
