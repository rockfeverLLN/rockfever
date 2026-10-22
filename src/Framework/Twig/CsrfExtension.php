<?php
/**.
 * User: Nicolas van de Walle
 * Date: 19-Feb-18
 * Time: 18:09
 */

namespace Framework\Twig;

use Framework\Middleware\CsrfMiddleware;
use Twig\TwigFunction;

class CsrfExtension extends \Twig\Extension\AbstractExtension
{

    /**
     * @var CsrfMiddleware
     */
    private $csrfMiddleware;

    public function __construct(CsrfMiddleware $csrfMiddleware)
    {
        $this->csrfMiddleware = $csrfMiddleware;
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('csrfInput', [$this,'csrfInput'], ['is_safe'=> ['html']]),
            new TwigFunction('csrfToken', [$this,'csrfToken'], ['is_safe'=> ['html']])
        ];
    }

    public function csrfInput()
    {
        return '<input type="hidden" ' .
                'name="' . $this->csrfMiddleware->getFormKey() . '" ' .
                'value="' . $this->csrfMiddleware->generateToken() . '"/>';
    }

    public function csrfToken()
    {
        return $this->csrfMiddleware->generateToken();
    }
}
