<?php
namespace Framework\Actions;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait RouterAwareAction
 * @package Framework\Actions
 */
trait RouterAwareAction
{

    /**
     * Returns a redirection response
     * @param string $path
     * @param array $params
     * @param array $queryParams
     * @return ResponseInterface
     */
    public function redirect(string $path, array $params = [], array $queryParams = []) : ResponseInterface
    {
        $redirectUri = $this->router->generateUri($path, $params, $queryParams);
        return (new Response())
            ->withStatus(302)
            ->withHeader('Location', $redirectUri);
    }
}
