<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 02-Apr-18
 */

namespace App\Main\Actions;

use App\Main\Settings;
use Framework\Renderer\RendererInterface;

class LegalAction
{
    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var Settings
     */
    private $settings;

    public function __construct(RendererInterface $renderer, Settings $settings)
    {
        $this->renderer = $renderer;
        $this->settings = $settings;
    }

    public function __invoke()
    {
        $roi = $this->settings->get('ROI');
        $terms = $this->settings->get('TERMS');
        return $this->renderer->render('@main/legal', compact('roi', 'terms'));
    }
}
