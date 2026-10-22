<?php
namespace App\Blog\Actions;

use App\Blog\Entity\Post;
use App\Blog\PostUpload;
use App\Blog\Table\CategoryTable;
use App\Blog\Table\PostTable;
use Framework\Actions\CrudAction;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Framework\Session\FlashService;
use Psr\Http\Message\ServerRequestInterface as Request;

class PostCrudAction extends CrudAction
{
    protected $viewPath = '@blog/admin/posts';

    protected $routePrefix = 'blog.admin';
    /**
     * @var CategoryTable
     */
    private $categoryTable;
    /**
     * @var PostUpload
     */
    private $postUpload;

    public function __construct(
        RendererInterface $renderer,
        Router $router,
        PostTable $table,
        FlashService $flash,
        CategoryTable $categoryTable,
        PostUpload $postUpload
    ) {
        parent::__construct($renderer, $router, $table, $flash);
        $this->categoryTable = $categoryTable;
        $this->postUpload = $postUpload;
    }

    public function delete(Request $request)
    {
        /** @var Post $post */
        $post = $this->table->find($request->getAttribute('id'));
        $this->postUpload->delete($post->image);
        return parent::delete($request);
    }

    /**
     * @param $params
     * @return array
     */
    protected function formParams(array $params): array
    {
        $params['categories'] = $this->categoryTable->findList();
        return $params;
    }

    /**
     * @param Request $request
     * @param Post $post
     * @return array
     */
    protected function getParams(Request $request, $post) : array
    {
        $params = array_merge($request->getParsedBody(), $request->getUploadedFiles());
        $image = $this->postUpload->upload($params['image'], $post->image);
        if ($image) {
            $params['image'] = $image;
        } else {
            unset($params['image']);
        }
        $params = array_filter($params, function ($key) {
            return in_array($key, ['name', 'slug', 'content', 'created_at', 'category_id', 'image', 'public']);
        }, ARRAY_FILTER_USE_KEY);

        $params = array_merge($params, [
            'updated_at'    =>  date('Y-m-d H:i:s')
        ]);
        return $params;
    }

    protected function getValidator(Request $request)
    {
        $validator = parent::getValidator($request)
            ->required('content', 'name', 'slug', 'created_at', 'category_id')
            ->length('content', 10)
            ->length('name', 2, 250)
            ->length('slug', 2, 70)
            ->exists('category_id', $this->categoryTable)
            ->slug('slug')
            ->extension('image', ['jpg', 'png'])
            ->dateTime('created_at');
        if (is_null($request->getAttribute('id'))) {
            $validator->uploaded('image');
        }
        return $validator;
    }

    protected function getNewEntity()
    {
        $post = new Post();
        $post->setCreatedAt(new \DateTime());
        return $post;
    }
}
