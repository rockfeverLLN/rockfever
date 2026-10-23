<?php
namespace Framework\Session;

interface SessionInterface
{

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param string $key
     * @param $value
     * @return mixed
     */
    public function set(string $key, $value) : void;

    /**
     * @param string $key
     * @return mixed
     */
    public function delete(string $key) : void;

    /**
     * Gives the session a new id, keeping what's in it: done at login, so an id someone made the visitor use
     * before doesn't get logged in with them
     */
    public function regenerate() : void;
}
