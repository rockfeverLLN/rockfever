<?php

namespace App\Events\Actions;

use App\Events\DancerNames;
use Framework\Actions\RouterAwareAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The page that tells the statistics which names are one dancer: its suggestions to settle, two names to match by hand,
 * and the choices made so far to undo. The flash messages never quote a name: they aren't escaped (flash.twig), and
 * anyone can sign up with any name.
 */
class StatisticsNamesAction
{
    use RouterAwareAction;

    public function __construct(
        private RendererInterface $renderer,
        private Router $router,
        private FlashService $flash,
        private DancerNames $names
    ) {
    }

    public function __invoke(ServerRequestInterface $request)
    {
        if ($request->getMethod() === 'POST') {
            $body = (array)$request->getParsedBody();
            // An answer from the list, saved without leaving the page (script.js), which removes its row
            if ($request->getHeaderLine('X-Requested-With') === 'fetch') {
                return new Response($this->answer((array)($body['pairs'] ?? [])) > 0 ? 204 : 409);
            }
            return $this->save($body);
        }

        $dancers = $this->names->dancers();
        return $this->renderer->render('@events/admin/club-names', [
            'dancers' => $dancers,
            'suggestions' => $this->names->suggestions($dancers),
            'choices' => $this->names->choices(),
        ]);
    }

    private function save(array $body): ResponseInterface
    {
        if (isset($body['undo'])) {
            $this->names->undo((int)$body['undo']);
            $this->flash->success('Le choix a bien été annulé');
        } elseif (isset($body['sure'])) {
            $settled = $this->names->mergeSure($this->names->dancers());
            $this->flash->success($settled > 1 ? "$settled paires de noms regroupées" : 'Une paire de noms regroupée');
        } elseif (isset($body['wrong'], $body['right'])) {
            $this->matchByHand((string)$body['wrong'], (string)$body['right']);
        } else {
            $this->settle((array)($body['pairs'] ?? []));
        }
        return $this->redirect('admin.statistics.names');
    }

    private function matchByHand(string $wrong, string $right): void
    {
        $dancers = $this->names->dancers();
        $wrong = $this->names->find($wrong, $dancers);
        $right = $this->names->find($right, $dancers);
        if ($wrong === null || $right === null) {
            $this->flash->error('Choisissez deux noms de la liste');
        } elseif ($wrong === $right) {
            $this->flash->error('Choisissez deux noms différents');
        } else {
            $this->names->merge($wrong, $right);
            $this->flash->success('Les deux noms comptent maintenant pour un seul danseur');
        }
    }

    /**
     * Without JavaScript, the page reloads after each answer
     *
     * @param array $pairs The suggestions of the form
     */
    private function settle(array $pairs): void
    {
        $settled = $this->answer($pairs);
        if ($settled === 0) {
            $this->flash->error('Choisissez une réponse pour au moins une paire de noms');
        } else {
            $this->flash->success($settled > 1 ? "$settled paires de noms réglées" : 'Une paire de noms réglée');
        }
    }

    /**
     * @param array $pairs The suggestions answered, those left unanswered stay for later
     * @return int How many were settled
     */
    private function answer(array $pairs): int
    {
        $dancers = $this->names->dancers();
        $settled = 0;
        foreach ($pairs as $pair) {
            $a = $pair['a'] ?? null;
            $b = $pair['b'] ?? null;
            if (!is_string($a) || !is_string($b) || !isset($dancers[$a], $dancers[$b])) {
                continue;
            }
            $choice = $pair['choice'] ?? null;
            if ($choice === 'a') {
                $this->names->merge($b, $a);
            } elseif ($choice === 'b') {
                $this->names->merge($a, $b);
            } elseif ($choice === 'different') {
                $this->names->separate($a, $b);
            } else {
                continue;
            }
            $settled++;
        }
        return $settled;
    }
}
