<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 31-Mar-18
 */

namespace App\Auth\Twig;

use Framework\Auth;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RoleExtension extends AbstractExtension
{
    /**
     * @var Auth
     */
    private $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('isAdmin', [$this, 'isAdmin']),
            new TwigFunction('isPhotographer', [$this, 'isPhotographer'])
        ];
    }

    public function isAdmin()
    {
        return ($this->auth->getUser() && $this->auth->getUser()->getRole() == "administrator");
    }

    /**
     * The photographers' login, which only opens the photo albums (App\Photos\PhotographerMiddleware)
     */
    public function isPhotographer()
    {
        return ($this->auth->getUser() && $this->auth->getUser()->getRole() == "photographer");
    }
}
