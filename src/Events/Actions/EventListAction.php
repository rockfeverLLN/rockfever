<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Events\Actions;

use App\Events\Table\DancerTable;
use Framework\Actions\FormAction;
use Framework\Database\QueryResult;
use Framework\Database\Table;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class EventListAction extends FormAction
{

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
        if ($id == 0) {
            return $this->renderer->render("404");
        }
        $dancers = self::getDancers($id, $this->table);
        return $this->renderer->render('@events/admin/list', compact('dancers', 'id'));
    }

    public function edit(ServerRequestInterface $request)
    {
        return $this->index($request);
    }

    public static function getDancers(int $id, Table $table) : QueryResult
    {
        return $table
            ->makeQuery()
            ->select('d.id', 'd.firstname', 'd.lastname', 'd.status')
            ->where('event_id = '.$id)
            ->order('d.lastname ASC')
            ->fetchAll();
    }
}
