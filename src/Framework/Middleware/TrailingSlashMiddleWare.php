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
                // 308 keeps a POST one: after a 301 clients ask again with GET, as for an MCP address pasted with its slash
                ->withStatus(in_array($request->getMethod(), ['GET', 'HEAD']) ? 301 : 308)
                ->withHeader('Location', substr($uri, 0, -1) . ($query !== '' ? "?$query" : ''));
        }

        return $next($request);
    }
}
