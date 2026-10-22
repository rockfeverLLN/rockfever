<?php
namespace Framework\Responses;

use GuzzleHttp\Psr7\Response;

class RedirectResponse extends Response
{
    public function __construct(string $url)
    {
        parent::__construct(302, ['Location'=> $url]);
    }
}
