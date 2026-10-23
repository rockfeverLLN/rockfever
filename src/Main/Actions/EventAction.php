<?php
namespace App\Main\Actions;

use App\Events\Entity\Dancer;
use App\Events\Table\DancerTable;
use App\Events\Table\EventTable;
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
     */
    public function __construct(
        RendererInterface $renderer,
        Router $router,
        EventTable $eventTable,
        FlashService $flash,
        DancerTable $dancerTable
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
        $event = $this->table->findNext();
        return $this->renderer->render($this->viewPath . '/event', compact('event'));
    }

    public function edit(ServerRequestInterface $request)
    {
        $item = $this->getNewEntity();
        if (!$this->table->findNext()->isRegistrationOpen()) {
            $this->flash->error("Les inscriptions ne sont pas ouvertes");
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
            return $this->redirect('event');
        } else {
            $this->flash->error("Tu n'as pas complété ton prénom et ton nom correctement");
        }

        $event = $this->table->findNext();
        return $this->renderer->render($this->viewPath . '/event', compact('event'));
    }

    protected function getParams(ServerRequestInterface $request, $item = null): array
    {
        $params = array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['firstname','lastname']);
        }, ARRAY_FILTER_USE_KEY);
        $params['event_id'] = $this->table->findNext()->id;
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
