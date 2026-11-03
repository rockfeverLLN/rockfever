<?php
namespace App\Events\Actions;

use App\Events\Table\SoireeTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use Framework\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The dates of the soirées, planned on /admin/events before their event: add one, change its week or cancel it,
 * remove it. Every page of the site takes them from App\Main\Calendar
 */
class SoireeAction
{
    use RouterAwareAction;

    public function __construct(private SoireeTable $table, private Router $router, private FlashService $flash)
    {
    }

    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($request->getMethod() === 'DELETE') {
            $this->table->delete((int) $id);
            $this->flash->success('La date a bien été supprimée');
            return $this->redirect('admin.events.index');
        }

        $body = $request->getParsedBody() ?? [];
        $week = trim((string) ($body['week'] ?? ''));
        $params = ['week' => $week === '' ? null : $week, 'cancelled' => empty($body['cancelled']) ? 0 : 1];
        $validator = (new Validator($body))->length('week', null, 10);

        if ($id === null) {
            $validator->required('date')->date('date');
            $date = (string) ($body['date'] ?? '');
            // Typed as d/m/Y, or Y-m-d from the date field
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $parts)) {
                $date = "$parts[3]-$parts[2]-$parts[1]";
            }
            if (!$validator->isValid()) {
                return $this->fail($validator);
            }
            if ($this->table->dateExists($date)) {
                $this->flash->error('Cette date est déjà dans le calendrier');
                return $this->redirect('admin.events.index');
            }
            $this->table->insert(['date' => $date] + $params);
            $this->flash->success('La date a bien été ajoutée');
            return $this->redirect('admin.events.index');
        }

        if (!$validator->isValid()) {
            return $this->fail($validator);
        }
        $this->table->update((int) $id, $params);
        $this->flash->success($params['cancelled'] ? 'La soirée est annoncée annulée' : 'La date a bien été modifiée');
        return $this->redirect('admin.events.index');
    }

    private function fail(Validator $validator) : ResponseInterface
    {
        $this->flash->error(implode(' ', array_map('strval', $validator->getErrors())));
        return $this->redirect('admin.events.index');
    }
}
