<?php
/**.
 * User: Nicolas van de Walle
 * Date: 25-Feb-18
 * Time: 14:04
 */

namespace App\Auth\Actions;

use App\Auth\DatabaseAuth;
use Framework\Actions\RouterAwareAction;
use Framework\Renderer\RendererInterface;
use Framework\Responses\RedirectResponse;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\Session\SessionInterface;
use Framework\Throttle;
use Psr\Http\Message\ServerRequestInterface;

class LoginAttemptAction
{
    /**
     * Wrong passwords allowed from one address before the form waits ATTEMPTS_DELAY seconds: the login page
     * lists the users, so only the password needs guessing
     */
    const ATTEMPTS = 5;

    const ATTEMPTS_DELAY = 900;

    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var DatabaseAuth
     */
    private $auth;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var Throttle
     */
    private $throttle;

    use RouterAwareAction;

    public function __construct(
        RendererInterface $renderer,
        DatabaseAuth $auth,
        Router $router,
        SessionInterface $session,
        Throttle $throttle
    ) {
        $this->renderer = $renderer;
        $this->auth = $auth;
        $this->router = $router;
        $this->session = $session;
        $this->throttle = $throttle;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        if ($this->throttle->isBlocked($request, 'login', self::ATTEMPTS, self::ATTEMPTS_DELAY)) {
            (new FlashService($this->session))->error('Trop d\'essais. Réessaie dans un quart d\'heure.');
            return $this->redirect('auth.login');
        }
        $params = $request->getParsedBody() ?? [];
        $role = $params['role'] ?? '';
        $password = $params['password'] ?? '';
        $user = is_string($role) && is_string($password) ? $this->auth->login($role, $password) : null;
        if ($user) {
            $this->throttle->clear($request, 'login');
            $path = $this->session->get('auth.redirect') ?: $this->router->generateUri('admin.dashboard');
            $this->session->delete('auth.redirect');
            return new RedirectResponse($path);
        } else {
            $this->throttle->fail($request, 'login', self::ATTEMPTS_DELAY);
            // Slows down bots trying passwords
            sleep(1);
            (new FlashService($this->session))->error('Invalid username or password');
            return $this->redirect('auth.login');
        }
    }
}
