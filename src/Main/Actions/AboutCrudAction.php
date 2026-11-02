<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 14-Mar-18
 */

namespace App\Main\Actions;

use App\Main\Entity\AboutSection;
use App\Main\Table\AboutTable;
use Framework\Actions\CrudAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class AboutCrudAction extends CrudAction
{
    protected $messages = [
        'create'    =>  'La section a bien été créée',
        'edit'      =>  'La section a bien été modifiée',
        'delete'    =>  'La section a bien été supprimée'
    ];

    protected $viewPath = '@main/admin/about';

    protected $routePrefix = 'admin.about';

    /**
     * @var AboutTable
     */
    protected $table;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        AboutTable $table,
        FlashService $flash
    ) {
        parent::__construct($renderer, $router, $table, $flash);
    }

    public function delete(ServerRequestInterface $request)
    {
        $table = $this->table;
        /** @var AboutSection $section */
        $section = $table->find($request->getAttribute('id'));
        /** @var AboutSection[] $higherSections */
        $higherSections = $this->table->findHigherPositions($section->position);

        foreach ($higherSections as $higherSection) {
            $higherSection->position = $higherSection->position - 1;
            $table->update($higherSection->id, (array) $higherSection);
        }
        return parent::delete($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @param AboutSection $section
     * @return array
     */
    protected function getParams(ServerRequestInterface $request, $section = null) : array
    {
        $params = $request->getParsedBody();

        $positionExists = !is_null($section);

        $params = array_filter($params, function ($key) {
            return in_array(
                $key,
                ['title', 'content']
            );
        }, ARRAY_FILTER_USE_KEY);

        if ($positionExists) {
            $params['position'] = $section->position;
        } else {
            $params['position'] = $this->table->findMaximumPosition() + 1;
        }
        return $params;
    }

    /**
     * @param ServerRequestInterface $request
     * @return \Framework\Validator
     */
    protected function getValidator(ServerRequestInterface $request)
    {
        $validator = parent::getValidator($request)
            ->required('title', 'content')
            ->length('title', 2, 250)
            ->length('content', 10);
        return $validator;
    }

    protected function getNewEntity()
    {
        $section = new AboutSection();
        $section->position = $this->table->findMaximumPosition() + 1;

        return $section;
    }
}
