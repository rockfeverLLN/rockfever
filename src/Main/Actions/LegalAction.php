<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 02-Apr-18
 */

namespace App\Main\Actions;

use App\Main\Table\ValueTable;
use Framework\Renderer\RendererInterface;

class LegalAction
{
    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var ValueTable
     */
    private $valueTable;

    public function __construct(RendererInterface $renderer, ValueTable $valueTable)
    {
        $this->renderer = $renderer;
        $this->valueTable = $valueTable;
    }

    public function __invoke()
    {
        $roi = $this->valueTable->getValue('ROI');
        $terms = $this->valueTable->getValue('TERMS');
        return $this->renderer->render('@main/legal', compact('roi', 'terms'));
    }
}
