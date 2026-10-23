<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 12-Mar-18
 */

namespace App\Events\Actions;

use App\Events\Table\EventTable;
use Framework\Actions\RouterAwareAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EventOpenAction
{
    use RouterAwareAction;
    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var EventTable
     */
    private $table;
    /**
     * @var Router
     */
    private $router;

    public function __construct(RendererInterface $renderer, EventTable $table, Router $router)
    {
        $this->renderer = $renderer;
        $this->table = $table;
        $this->router = $router;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        $this->table->update($id, ['closed' => 0]);
        return $this->redirect('admin.events.index');
    }
}
