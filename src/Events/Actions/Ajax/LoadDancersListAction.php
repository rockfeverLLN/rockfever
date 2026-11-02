<?php
/**.
 * User: Nicolas van de Walle
 * Date: 15-Mar-19
 * Time: 19:16
 */

namespace App\Events\Actions\Ajax;

use App\Events\Actions\EventListAction;
use App\Events\Table\DancerTable;
use Framework\Actions\AjaxAction;
use Framework\Renderer\RendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class LoadDancersListAction extends AjaxAction
{

    /**
     * @var RendererInterface
     */
    private $renderer;

    public function __construct(DancerTable $table, RendererInterface $renderer)
    {
        parent::__construct($table);
        $this->renderer = $renderer;
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function index(Request $request)
    {
        $id = $request->getAttribute('id');
        $dancers = EventListAction::getDancers($id, $this->table);
        return $this->renderer->render('@events/admin/ajax/dancers-list', compact('dancers'));
    }
}
