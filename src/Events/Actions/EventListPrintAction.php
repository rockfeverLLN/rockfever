<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Events\Actions;

use App\Events\Table\DancerTable;
use Framework\Actions\FormAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class EventListPrintAction extends FormAction
{
    /**
     * @var DancerTable
     */
    protected $table;
    /**
     * EventListAction constructor.
     * @param RendererInterface $renderer
     * @param Router $router
     * @param DancerTable $table
     * @param FlashService $flash
     */
    public function __construct(
        RendererInterface $renderer,
        Router $router,
        DancerTable $table,
        FlashService $flash
    ) {
        parent::__construct($renderer, $router, $table, $flash);
    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    public function index(ServerRequestInterface $request) : string
    {
        $id = $request->getAttribute('id');
        $type = $request->getQueryParams()['type'] ?? null;
        if ($type === "registered") {
            $dancers = $this->table->findAllRegistered($id);
        } elseif ($type == "attendees") {
            $dancers = $this->table->findAllAttendees($id);
        } elseif ($type == "all") {
            $dancers = $this->table->findAllPerEvent($id);
        } else {
            $dancers = [];
        }
        return $this->renderer->render('@events/admin/list.print', compact('dancers', 'id', 'type'));
    }

    public function edit(ServerRequestInterface $request)
    {
        return parent::edit($request);
    }
}
