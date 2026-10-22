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
            new TwigFunction('isAdmin', [$this, 'isAdmin'])
        ];
    }

    public function isAdmin()
    {
        return ($this->auth->getUser() && $this->auth->getUser()->getRole() == "administrator");
    }
}
