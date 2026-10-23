<?php
/**.
 * User: Nicolas van de Walle
 * Date: 23-Mar-19
 * Time: 12:53
 */

namespace App\Events\Actions;

use App\Events\Actions\Ajax\LoadDancersStatsAction;
use App\Events\Table\DancerTable;
use App\Events\Table\EventTable;
use Framework\Actions\RouterAwareAction;
use Framework\Database\NoRecordException;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Psr\Http\Message\ServerRequestInterface;

class EventStatisticsAction
{
    /**
     * @var DancerTable
     */
    public $dancerTable;
    /**
     * @var RendererInterface
     */
    private $renderer;
    /**
     * @var Router
     */
    private $router;
    /**
     * @var EventTable
     */
    private $eventTable;
    /**
     * @var LoadDancersStatsAction
     */
    private $loadDancersStatsAction;

    use RouterAwareAction;

    public function __construct(
        DancerTable $dancerTable,
        RendererInterface $renderer,
        Router $router,
        EventTable $eventTable,
        LoadDancersStatsAction $loadDancersStatsAction
    ) {
        $this->dancerTable = $dancerTable;
        $this->renderer = $renderer;
        $this->router = $router;
        $this->eventTable = $eventTable;
        $this->loadDancersStatsAction = $loadDancersStatsAction;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        try {
            $event = $this->eventTable->find($id);
        } catch (NoRecordException $e) {
            return $this->redirect('admin.events.index');
        }

        $stats = json_decode($this->loadDancersStatsAction->index($request));
        $registered = (int)$stats[0];
        $here = (int)$stats[1];
        $vip = (int)$stats[2];
        $improvised = (int)$stats[3];

        $arrivalTimesSQL = $this->dancerTable->findArrivalTimes($id);

        $arrivalTimes = [];
        for($i=2000; $i<=2400; $i++) {
            if ($i%100==60) {
                $i+=40;
            }
            $arrivalTimes[$i] = 0;
        }

        foreach ($arrivalTimesSQL as $dancer) {
            $arrivalTimes[$dancer->time] = (int)$dancer->amount;
        }

        return $this->renderer->render(
            "@events/admin/statistics",
            compact('id', 'event', 'registered', 'here', 'vip', 'improvised', 'arrivalTimes')
        );
    }
}
