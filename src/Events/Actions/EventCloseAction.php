<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Events\Actions;

use App\Events\Table\EventTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EventCloseAction
{
    use RouterAwareAction;
    /**
     * @var EventTable
     */
    private $table;
    /**
     * @var Router
     */
    private $router;

    public function __construct(EventTable $table, Router $router)
    {
        $this->table = $table;
        $this->router = $router;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        $id = $request->getAttribute('id');

        $this->table->update($id, ['closed' => 1]);
        return $this->redirect('admin.events.participants', ["id"=>$id]);
    }
}
