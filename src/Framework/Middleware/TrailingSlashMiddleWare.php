<?php

namespace Framework\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

class TrailingSlashMiddleWare
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $uri = $request->getUri()->getPath();

        if (!empty($uri) && $uri[-1] === "/" && mb_strlen($uri)!==1) {
            // Keep the query string: links shared on social media carry utm_ tags that Analytics reads from the URL
            $query = $request->getUri()->getQuery();
            return (new Response())
                ->withStatus(301)
                ->withHeader('Location', substr($uri, 0, -1) . ($query !== '' ? "?$query" : ''));
        }

        return $next($request);
    }
}
