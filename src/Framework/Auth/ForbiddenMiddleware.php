<?php
/**.
 * User: Nicolas van de Walle
 * Date: 25-Feb-18
 * Time: 15:23
 */

namespace Framework\Auth;

use App\Events\Exception\NoNextEventException;
use Framework\Responses\RedirectResponse;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ForbiddenMiddleware implements MiddlewareInterface
{

    /**
     * @var string
     */
    private $loginPath;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var Router
     */
    private $router;

    public function __construct(string $loginPath, SessionInterface $session, Router $router)
    {
        $this->loginPath = $loginPath;
        $this->session = $session;
        $this->router = $router;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ForbiddenException $exception) {
            $this->session->set('auth.redirect', $request->getUri()->getPath());
            (new FlashService($this->session))->error('Vous devez être connecté pour accéder à cette page');
            return new RedirectResponse($this->loginPath);
        } catch (AdminOnlyException $exception) {
            (new FlashService($this->session))->error('Vous devez être administrateur pour accéder à cette page');
            return new RedirectResponse($this->router->generateUri('admin.dashboard'));
        } catch (NoNextEventException $exception) {
            (new FlashService($this->session))->error('Aucun événement n\'a été programmé');
            return new RedirectResponse($this->router->generateUri('home'));
        }
    }
}
