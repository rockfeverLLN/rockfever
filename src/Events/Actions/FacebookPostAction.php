<?php
namespace App\Events\Actions;

use App\Events\FacebookPosts;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

/**
 * /admin/ajax/facebook-post?url=…: the text of the Page's post at that link, as {message} or {error}, for the event
 * form to put in the description
 */
class FacebookPostAction
{
    public function __construct(private FacebookPosts $posts)
    {
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $url = trim((string) ($request->getQueryParams()['url'] ?? ''));
        try {
            $data = ['message' => $this->posts->findMessage($url)];
        } catch (\RuntimeException $e) {
            $data = ['error' => $e->getMessage()];
        }
        return new Response(200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ], json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
