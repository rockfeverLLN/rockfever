<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 03-Mar-18
 */

namespace App\Events\Actions;

use App\Events\Entity\Event;
use App\Events\Table\EventTable;
use App\Main\Table\ValueTable;
use Framework\Actions\CrudAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class EventCrudAction extends CrudAction
{
    protected $messages = [
        'create'    =>  'L\'événement a bien été créé',
        'edit'      =>  'L\'événement a bien été modifié',
        'delete'    =>  'L\'événement a bien été supprimé'
    ];

    protected $viewPath = '@events/admin';

    protected $routePrefix = 'admin.events';
    /**
     * @var ValueTable
     */
    private $valueTable;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        EventTable $table,
        FlashService $flash,
        ValueTable $valueTable
    ) {
        parent::__construct($renderer, $router, $table, $flash);
        $this->valueTable = $valueTable;
    }

    /**
     * @param ServerRequestInterface $request
     * @param $event
     * @return array
     */
    protected function getParams(ServerRequestInterface $request, $event) : array
    {
        $params = $request->getParsedBody();
        $params = array_filter($params, function ($key) {
            return in_array(
                $key,
                [
                    'title',
                    'description',
                    'date',
                    'waltz_time',
                    'rock_time',
                    'place',
                    'price',
                    'facebook_link',
                    'covevent_link'
                ]
            );
        }, ARRAY_FILTER_USE_KEY);

        $explodedDate = explode('/', $params['date']);
        $params['date'] = join('-', array_reverse($explodedDate));

        return $params;
    }

    /**
     * @param ServerRequestInterface $request
     * @return \Framework\Validator
     */
    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required(
                'title',
                'description',
                'date',
                'waltz_time',
                'rock_time',
                'place',
                'price',
                'facebook_link',
                'covevent_link'
            )
            ->length('title', 2, 250)
            ->length('description', 100)
            ->date('date')
            ->time('waltz_time')
            ->time('rock_time')
            ->facebookEventUrl('facebook_link');
        return $validator;
    }

    protected function getNewEntity()
    {
        $event = new Event();
        $event->setWaltzTime($this->valueTable->getValue('DEFAULT_WALTZTIME'));
        $event->setRockTime($this->valueTable->getValue('DEFAULT_ROCKTIME'));
        $event->place = $this->valueTable->getValue('DEFAULT_PLACE');
        $event->price = $this->valueTable->getValue('DEFAULT_PRICE');
        return $event;
    }
}
