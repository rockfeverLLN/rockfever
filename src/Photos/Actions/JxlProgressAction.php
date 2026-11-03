<?php

namespace App\Photos\Actions;

use App\Photos\JxlProgress;
use Framework\Background;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * How far the JPEG XL copies are, for the dashboard's progress bar, which asks every few seconds. While some are
 * missing, it makes them for a while once this answer is sent. In the admin outside /photos: the photographers'
 * login can't open it (PhotographerMiddleware)
 */
class JxlProgressAction
{
    /**
     * @var JxlProgress
     */
    private $progress;

    public function __construct(JxlProgress $progress)
    {
        $this->progress = $progress;
    }

    public function __invoke() : ResponseInterface
    {
        $status = $this->progress->status();
        if ($status['done'] < $status['total']) {
            Background::defer(fn () => $this->progress->catchUp(20));
        }
        return new Response(200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store'
        ], json_encode($status));
    }
}
