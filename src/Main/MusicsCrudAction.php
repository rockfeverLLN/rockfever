<?php
/**.
 * User: Nicolas van de Walle
 * Date: 21-Aug-18
 * Time: 21:58
 */

namespace App\Main;

use App\Main\Entity\Music;
use App\Main\Table\MusicTable;
use Framework\Actions\CrudAction;
use Framework\Database\NoRecordException;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface;

class MusicsCrudAction extends CrudAction
{
    protected $messages = [
        'create'    =>  'La musique a bien été créée',
        'read'      =>  'La musique a bien été marquée comme vue',
        'unread'    =>  'La musique a bien été marquée comme non vue',
        'delete'    =>  'La musique a bien été supprimée',
        'notFound'  =>  'La musique à laquelle vous tentez d\'accéder n\'existe pas'
    ];

    protected $viewPath = '@main/admin/music';

    protected $routePrefix = 'admin.music';

    /**
     * MusicsCrudAction constructor.
     * @param RendererInterface $renderer
     * @param Router $router
     * @param MusicTable $table
     * @param FlashService $flash
     */
    public function __construct(
        RendererInterface $renderer,
        Router $router,
        MusicTable $table,
        FlashService $flash
    ) {
        parent::__construct($renderer, $router, $table, $flash);
    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    public function index(ServerRequestInterface $request) : string
    {
        $params = $request->getQueryParams();
        $items = $this->table->findAll()->paginate(15, $params['p'] ?? 1);
        $p = $params['p']?? 1;
        return $this->renderer->render($this->viewPath . '/index', compact('items', 'p'));
    }

    public function create(ServerRequestInterface $request)
    {
        return $this->renderer->render('@main/music');
    }

    public function edit(ServerRequestInterface $request)
    {
        try {
            /** @var Music $item */
            $item = $this->table->find($request->getAttribute('id'));
            if ($item->added == 0) {
                $this->flash->success($this->messages['read']);
                $this->table->update($item->id, ['added' => 1]);
            } else {
                $this->flash->success($this->messages['unread']);
                $this->table->update($item->id, ['added' => 0]);
            }
        } catch (NoRecordException $e) {
            $this->flash->error($this->messages['notFound']);
        }

        return $this->redirect($this->routePrefix . '.index', [], ['p'=>$request->getQueryParams()['p'] ?? null]);
    }
}
