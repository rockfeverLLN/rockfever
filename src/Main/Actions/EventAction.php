<?php
namespace App\Main\Actions;

use App\Events\Entity\Dancer;
use App\Events\Table\DancerTable;
use App\Events\Table\EventTable;
use App\Main\Calendar;
use Framework\Actions\FormAction;
use Framework\Database\Hydrator;
use Framework\Database\NoRecordException;
use Framework\Database\Table;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class EventAction extends FormAction
{

    protected $viewPath = '@main';

    protected $messages = [
        'create'=>  "Ton inscription a bien été enregistrée"
    ];
    /**
     * @var DancerTable
     */
    private $dancerTable;

    /**
     * HomeAction constructor.
     * @param RendererInterface $renderer
     * @param Router $router
     * @param EventTable $eventTable
     * @param FlashService $flash
     * @param DancerTable $dancerTable
     * @param Calendar $calendar
     */
    public function __construct(
        RendererInterface $renderer,
        Router $router,
        EventTable $eventTable,
        FlashService $flash,
        DancerTable $dancerTable,
        private Calendar $calendar
    ) {
        parent::__construct($renderer, $router, $eventTable, $flash);
        $this->dancerTable = $dancerTable;
    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    public function index(ServerRequestInterface $request) : string
    {
        return $this->render();
    }

    public function edit(ServerRequestInterface $request)
    {
        $item = $this->getNewEntity();
        // The next soirée's sign-up, as every page shows it (Calendar): NextEventMiddleware made sure there is one
        if ($this->calendar->getNext()['registration']['status'] !== Calendar::REGISTRATION_OPEN) {
            $this->flash->error("Les inscriptions à cette soirée ne sont pas ouvertes");
            return $this->redirect('event');
        }
        $validator = $this->getValidator($request);
        if ($validator->isValid()) {
            $params = $this->getParams($request, $item);
            try {
                $this->dancerTable->findDuplicates($params['firstname'], $params['lastname'], $params['event_id']);
            } catch (NoRecordException $e) {
                $this->dancerTable->insert($params);
            }
            $this->flash->success($this->messages['create']);
            $this->flash->event('sign_up');
            return $this->redirect('event');
        } else {
            $this->flash->error("Tu n'as pas complété ton prénom et ton nom correctement");
        }

        return $this->render();
    }

    private function render() : string
    {
        $soiree = $this->calendar->getNext();
        return $this->renderer->render($this->viewPath . '/event', compact('soiree'));
    }

    protected function getParams(ServerRequestInterface $request, $item = null): array
    {
        $params = array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['firstname','lastname']);
        }, ARRAY_FILTER_USE_KEY);
        $params['event_id'] = $this->calendar->getNext()['event']->id;
        return $params;
    }

    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('firstname', 'lastname')
            ->notEmpty('firstname', 'lastname');
        return $validator;
    }

    protected function getNewEntity()
    {
        $dancer = new Dancer();
        return $dancer;
    }
}
