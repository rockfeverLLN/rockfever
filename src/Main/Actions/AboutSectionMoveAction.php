<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 15-Mar-18
 */

namespace App\Main\Actions;

use App\Main\Entity\AboutSection;
use App\Main\Table\AboutTable;
use Framework\Actions\RouterAwareAction;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AboutSectionMoveAction
{
    use RouterAwareAction;

    /**
     * @var FlashService
     */
    private $flashService;
    /**
     * @var Router
     */
    private $router;
    /**
     * @var AboutTable
     */
    private $table;

    public function __construct(FlashService $flashService, Router $router, AboutTable $table)
    {
        $this->flashService = $flashService;
        $this->router = $router;
        $this->table = $table;
    }

    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        if (array_key_exists('moveup', $request->getParsedBody())) {
            $this->moveUp($request);
            return $this->redirect('admin.about.index');
        } elseif (array_key_exists('movedown', $request->getParsedBody())) {
            $this->moveDown($request);
            return $this->redirect('admin.about.index');
        } else {
            $this->flashService->error("Impossible d'effectuer l'action demandée");
            return $this->redirect('admin.about.index');
        }
    }

    /**
     * Decreases the position number
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function moveUp(ServerRequestInterface $request) : ResponseInterface
    {
        /** @var AboutSection $section */
        $section = $this->table->find($request->getAttribute('id'));
        if ($section && $section->position > 1 && $section->position <= $this->table->findMaximumPosition()) {
            $upperSection = $this->table->findBy('position', $section->position - 1);
            $this->table->update($section->id, ['position' => $section->position - 1]);
            $this->table->update($upperSection->id, ['position' => $section->position]);
            $this->flashService->success("La section a bien été déplacée");
        }
        return $this->redirect('admin.about.index');
    }

    /**
     * Increases the position number
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function moveDown(ServerRequestInterface $request) : ResponseInterface
    {
        /** @var AboutSection $section */
        $section = $this->table->find($request->getAttribute('id'));
        if ($section && $section->position > 0 && $section->position < $this->table->findMaximumPosition()) {
            $upperSection = $this->table->findBy('position', $section->position + 1);
            $this->table->update($section->id, ['position' => $section->position + 1]);
            $this->table->update($upperSection->id, ['position' => $section->position]);
            $this->flashService->success("La section a bien été déplacée");
        }
        return $this->redirect('admin.about.index');
    }
}
