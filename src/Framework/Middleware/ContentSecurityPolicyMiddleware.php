<?php
namespace Framework\Middleware;

use Framework\Renderer\RendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets a page run only the scripts it carries itself. Every request gets a random nonce ({{ csp_nonce }} in the
 * templates); a page whose HTML uses it gets a Content-Security-Policy that blocks every other script, so a script
 * slipped into a page can't run. The scripts those scripts add (Google's tag, script.js) are allowed by
 * 'strict-dynamic'. The admin doesn't use the nonce (TinyMCE, onsubmit attributes): it only has www/.htaccess's policy,
 * which browsers apply together with this one
 */
class ContentSecurityPolicyMiddleware
{
    /**
     * @var RendererInterface
     */
    private $renderer;

    public function __construct(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $nonce = base64_encode(random_bytes(16));
        $this->renderer->addGlobal('csp_nonce', $nonce);
        /** @var ResponseInterface $response */
        $response = $next($request);
        // Pages rendered from a string have no Content-Type yet: PHP sends text/html. Files and zips are left alone
        $type = $response->getHeaderLine('Content-Type') ?: 'text/html';
        if (!str_starts_with($type, 'text/html') || !str_contains((string) $response->getBody(), 'nonce="' . $nonce . '"')) {
            return $response;
        }
        return $response->withAddedHeader('Content-Security-Policy', "script-src 'nonce-$nonce' 'strict-dynamic'");
    }
}
