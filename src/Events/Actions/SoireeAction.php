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
 * The dates of the soirées, planned on /admin/events before their event: add one (or one a week until a date), change
 * its week or cancel it, remove it. Every page of the site takes them from App\Main\Calendar
 */
class SoireeAction
{
    use RouterAwareAction;

    // At most a year of weekly dates in one go
    const MAX_WEEKS = 53;

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
            $first = self::day((string) ($body['date'] ?? ''));
            $until = self::day((string) ($body['until'] ?? ''));
            if ($until !== '') {
                $validator->date('until');
            }
            if (!$validator->isValid()) {
                return $this->fail($validator);
            }
            return $this->add($first, $until ?: $first, $params['week']);
        }

        if (!$validator->isValid()) {
            return $this->fail($validator);
        }
        $this->table->update((int) $id, $params);
        $this->flash->success($params['cancelled'] ? 'La soirée est annoncée annulée' : 'La date a bien été modifiée');
        return $this->redirect('admin.events.index');
    }

    /**
     * Adds the date, and if $until is later, the same weekday every week until then: a whole quadrimestre at once. A
     * week like "S3" goes on with S4, S5…; the dates already planned are left as they are
     */
    private function add(string $first, string $until, ?string $week) : ResponseInterface
    {
        $date = new \DateTimeImmutable($first);
        $last = new \DateTimeImmutable($until);
        if ($last < $date) {
            $this->flash->error('La dernière date est avant la première');
            return $this->redirect('admin.events.index');
        }
        $added = 0;
        $skipped = 0;
        for ($i = 0; $date <= $last && $i < self::MAX_WEEKS; $i++, $date = $date->modify('+1 week')) {
            if ($this->table->dateExists($date->format('Y-m-d'))) {
                $skipped++;
            } else {
                $this->table->insert(['date' => $date->format('Y-m-d'), 'week' => $week, 'cancelled' => 0]);
                $added++;
            }
            if ($week !== null && preg_match('/^(.*?)(\d+)$/', $week, $parts)) {
                $week = $parts[1] . ((int) $parts[2] + 1);
            }
        }
        if ($added === 0) {
            $this->flash->error($skipped > 1 ? 'Ces dates sont déjà dans le calendrier' : 'Cette date est déjà dans le calendrier');
        } else {
            $this->flash->success(
                ($added === 1 ? 'La date a bien été ajoutée' : "$added dates ont bien été ajoutées")
                . ($skipped ? " ($skipped déjà dans le calendrier)" : '')
            );
        }
        return $this->redirect('admin.events.index');
    }

    /**
     * Y-m-d, from the date field or typed as d/m/Y
     */
    private static function day(string $date) : string
    {
        return preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $parts) ? "$parts[3]-$parts[2]-$parts[1]" : $date;
    }

    private function fail(Validator $validator) : ResponseInterface
    {
        $this->flash->error(implode(' ', array_map('strval', $validator->getErrors())));
        return $this->redirect('admin.events.index');
    }
}
