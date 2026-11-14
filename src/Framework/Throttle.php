<?php

namespace Framework;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Counts failed attempts (wrong passwords) per visitor's IP address, in files under tmp/throttle, so a bot can't
 * start over by dropping its session cookie. Each file holds the times of an address's recent failures.
 */
class Throttle
{
    /**
     * @var string
     */
    private $path;

    public function __construct(?string $path = null)
    {
        // From __DIR__: never the current folder, which PHP on OVH once couldn't resolve
        $this->path = $path ?? dirname(__DIR__, 2) . '/tmp/throttle';
    }

    /**
     * Whether the visitor failed $max times or more in the last $seconds
     */
    public function isBlocked(ServerRequestInterface $request, string $scope, int $max, int $seconds) : bool
    {
        return count($this->update($request, $scope, $seconds, false)) >= $max;
    }

    public function fail(ServerRequestInterface $request, string $scope, int $seconds) : void
    {
        $this->update($request, $scope, $seconds, true);
        if (mt_rand(1, 100) === 1) {
            $this->removeOldFiles();
        }
    }

    public function clear(ServerRequestInterface $request, string $scope) : void
    {
        @unlink($this->getFile($request, $scope));
    }

    /**
     * @return int[] The times of the failures in the last $seconds, after adding one now if $add
     */
    private function update(ServerRequestInterface $request, string $scope, int $seconds, bool $add) : array
    {
        $file = $this->getFile($request, $scope);
        if (!$add && !is_file($file)) {
            return [];
        }
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0777, true);
        }
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return [];
        }
        // Several requests at once from the same bot all count
        flock($handle, LOCK_EX);
        $times = json_decode(stream_get_contents($handle), true);
        $times = array_values(array_filter(is_array($times) ? $times : [], fn ($time) => $time > time() - $seconds));
        if ($add) {
            $times[] = time();
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($times));
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        return $times;
    }

    private function getFile(ServerRequestInterface $request, string $scope) : string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        return $this->path . '/' . hash('sha256', $scope . '|' . $ip);
    }

    /**
     * Addresses that stopped trying a day ago
     */
    private function removeOldFiles() : void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) {
            if (filemtime($file) < time() - 24 * 3600) {
                @unlink($file);
            }
        }
    }
}
