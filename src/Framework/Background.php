<?php
namespace Framework;

/**
 * Work done once the response is sent, so whoever made the request doesn't wait for it (the JPEG XL copies of
 * uploaded photos). Needs PHP-FPM, as on the server: elsewhere (php -S, scripts) the work is done at the end of
 * the request, before the response is complete
 */
class Background
{

    /**
     * @var callable[]
     */
    private static $tasks = [];

    public static function defer(callable $task) : void
    {
        if (!self::$tasks) {
            register_shutdown_function([self::class, 'run']);
        }
        self::$tasks[] = $task;
    }

    /**
     * @internal Called by PHP at the end of the request
     */
    public static function run() : void
    {
        // The session's lock would hold the visitor's next request (the next photo of an upload) until this ends
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        ignore_user_abort(true);
        set_time_limit(300);
        while ($task = array_shift(self::$tasks)) {
            try {
                $task();
            } catch (\Throwable $e) {
                // The copy is made again the next time it's asked for
            }
        }
    }
}
