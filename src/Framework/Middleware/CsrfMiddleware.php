<?php

namespace Framework\Middleware;

use Framework\Exception\CsrfInvalidException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Refuses forms sent from another site. Browsers say where a request comes from (Sec-Fetch-Site, or Origin for
 * older ones), and a page elsewhere can't change that. No token in the session: the one-time tokens this used to
 * check failed on phones that reopened a page from their cache, after the session had expired, and on the photo
 * uploads sending one request per photo.
 *
 * Requests without either header don't come from a browser (curl, the smoke test), so another site can't make
 * a visitor's browser send them.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @throws CsrfInvalidException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $handler->handle($request);
        }
        // Assistants call it from their own sites: it has its own list of them (McpAction::ORIGINS)
        if ($request->getUri()->getPath() === '/mcp') {
            return $handler->handle($request);
        }
        $site = $request->getHeaderLine('Sec-Fetch-Site');
        if ($site !== '') {
            // "none": typed in the address bar or opened from a bookmark
            if (!in_array($site, ['same-origin', 'none'])) {
                throw new CsrfInvalidException();
            }
            return $handler->handle($request);
        }
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $request->getUri()->getHost()) {
            throw new CsrfInvalidException();
        }
        return $handler->handle($request);
    }
}
