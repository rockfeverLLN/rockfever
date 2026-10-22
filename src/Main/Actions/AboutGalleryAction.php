<?php
/**.
 * User: Nicolas van de Walle
 * Date: 23-Aug-18
 * Time: 11:08
 */

namespace App\Main\Actions;

use App\Main\AboutUpload;
use Framework\Actions\FormAction;
use Framework\Database\Table;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutGalleryAction extends FormAction
{
    public $messages = [
        'edit'  =>  'L\'image a bien été uploadée. Voici son URL : '
    ];

    protected $viewPath = "@main/admin/about/gallery";
    /**
     * @var AboutUpload
     */
    private $aboutUpload;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        Table $table,
        FlashService $flash,
        AboutUpload $aboutUpload
    ) {
        parent::__construct($renderer, $router, $table, $flash);
        $this->aboutUpload = $aboutUpload;
    }

    public function index(Request $request): string
    {
        $images = scandir(dirname(dirname(dirname(__DIR__))) . "/www/images/about");
        unset($images[0]);
        unset($images[1]);
        return $this->renderer->render($this->viewPath . '/index', compact("images"));
    }

    public function edit(Request $request)
    {
        $validator = $this->getValidator($request);
        if ($validator->isValid()) {
            $image = $this->aboutUpload->upload($request->getUploadedFiles()['image']);
            if ($image != null) {
                $this->flash->success(
                    '<a class="btn-floating right waves-effect waves-light red to-clipboard" ' .
                    'data-clipboard-text="/images/about/'.$image.'">' .
                    '<i class="material-icons">content_copy</i>' .
                    '</a>' .
                    $this->messages['edit'] . "/images/about/" . $image
                );
            }
        } else {
            $this->flash->error("L'image n'est pas valide");
        }

        return $this->redirect('admin.about.gallery');
    }

    protected function getValidator(Request $request)
    {
        return parent::getValidator($request)->uploaded('image');
    }
}
