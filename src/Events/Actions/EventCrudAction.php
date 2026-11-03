<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 03-Mar-18
 */

namespace App\Events\Actions;

use App\Events\Entity\Event;
use App\Events\Table\EventTable;
use App\Main\Calendar;
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

    /**
     * The day (Y-m-d) the new event is for, from the "Créer l'événement" of a planned date
     */
    private ?string $newDate = null;

    private EventTable $events;

    // How many past events a page can list (?n=), the first by default
    const PER_PAGE = [10, 25, 50, 100];

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        EventTable $table,
        FlashService $flash,
        ValueTable $valueTable,
        private Calendar $calendar
    ) {
        parent::__construct($renderer, $router, $table, $flash);
        $this->valueTable = $valueTable;
        $this->events = $table;
    }

    /**
     * Two tabs: the soirées to come (/admin/events), each planned date with its event once created, and the events
     * that are over (/admin/events/past)
     */
    public function index(ServerRequestInterface $request) : string
    {
        if (str_ends_with($request->getUri()->getPath(), '/past')) {
            $query = $request->getQueryParams();
            $perPage = in_array((int) ($query['n'] ?? 0), self::PER_PAGE, true) ? (int) $query['n'] : self::PER_PAGE[0];
            $items = $this->events->findPast()->paginate($perPage, (int) ($query['p'] ?? 1) ?: 1);
            $choices = self::PER_PAGE;
            return $this->renderer->render($this->viewPath . '/past', compact('items', 'perPage', 'choices'));
        }
        $soirees = array_values(array_filter($this->calendar->all(), fn (array $soiree) => !$soiree['past']));
        $participants = $this->events->countUpcomingParticipants();
        return $this->renderer->render($this->viewPath . '/index', compact('soirees', 'participants'));
    }

    public function create(ServerRequestInterface $request)
    {
        $date = $request->getQueryParams()['date'] ?? '';
        $this->newDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        return parent::create($request);
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
                    'covevent_link',
                    'photos_password'
                ]
            );
        }, ARRAY_FILTER_USE_KEY);
        // Empty: the soirée's album is only for the team
        $params['photos_password'] = trim($params['photos_password'] ?? '') ?: null;

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
        if (trim($request->getParsedBody()['photos_password'] ?? '') !== '') {
            $validator->length('photos_password', 4, 100);
        }
        return $validator;
    }

    protected function getNewEntity()
    {
        $event = new Event();
        $event->setWaltzTime($this->valueTable->getValue('DEFAULT_WALTZTIME'));
        $event->setRockTime($this->valueTable->getValue('DEFAULT_ROCKTIME'));
        $event->place = $this->valueTable->getValue('DEFAULT_PLACE');
        $event->price = $this->valueTable->getValue('DEFAULT_PRICE');
        if ($this->newDate !== null) {
            $event->setDate($this->newDate);
        }
        return $event;
    }
}
