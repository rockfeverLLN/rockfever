<?php
namespace App\Events;

/**
 * The posts of the Rock Fever Facebook Page, read with the Graph API and the Page's token ('facebook.page_token' in
 * config/database.php). The event form uses it to fill a soirée's description from the post that announces it.
 * Facebook doesn't let the site read events (only its Marketing Partners can), so it has to be a post
 */
class FacebookPosts
{
    private const GRAPH = 'https://graph.facebook.com';

    // How far back a pasted link is looked for
    private const LIMIT = 50;

    public function __construct(private string $token)
    {
    }

    public function isConfigured() : bool
    {
        return $this->token !== '';
    }

    /**
     * The text of the Page's post at that link (any link Facebook gives for it: /posts/pfbid…, permalink.php,
     * photo.php, /share/p/…)
     * @throws \RuntimeException with the reason, in French, when it can't
     */
    public function findMessage(string $url) : string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Le site n\'est pas encore relié à la page Facebook (voir le README).');
        }
        if (!preg_match('#^https?://([a-z0-9-]+\.)*(facebook\.com|fb\.watch)/#i', $url)) {
            throw new \RuntimeException('Ce n\'est pas un lien Facebook.');
        }
        if (preg_match('#facebook\.com/events/#', $url)) {
            throw new \RuntimeException(
                'C\'est le lien d\'un événement : Facebook ne laisse pas lire les événements. Colle le lien du post.'
            );
        }
        if (preg_match('#(fb\.watch|facebook\.com/share)/#', $url)) {
            $url = $this->resolve($url);
        }
        foreach ($this->latest() as $post) {
            if (self::matches($url, $post)) {
                $message = trim($post['message'] ?? '');
                if ($message === '') {
                    throw new \RuntimeException('Ce post n\'a pas de texte.');
                }
                return $message;
            }
        }
        throw new \RuntimeException(
            'Ce post n\'est pas parmi les ' . self::LIMIT . ' derniers de la page Rock Fever.'
        );
    }

    /**
     * The Page's latest posts, newest first
     * @return array[] id, message, permalink_url, created_time
     */
    public function latest() : array
    {
        $body = $this->get(self::GRAPH . '/me/posts?' . http_build_query([
            'fields' => 'id,message,permalink_url,created_time',
            'limit' => self::LIMIT,
            'access_token' => $this->token,
        ]));
        $data = json_decode($body, true);
        if (!is_array($data) || isset($data['error'])) {
            $error = $data['error']['message'] ?? 'pas de réponse';
            throw new \RuntimeException('Facebook a refusé la demande : ' . $error);
        }
        return $data['data'] ?? [];
    }

    /**
     * The link is that post's: the same pfbid, or one of its numbers (post id, story_fbid, photo fbid), the Page's
     * own id aside since every post has it
     */
    private static function matches(string $url, array $post) : bool
    {
        $permalink = $post['permalink_url'] ?? '';
        if (preg_match('/pfbid\w+/', $url, $pfbid)) {
            return preg_match('/pfbid\w+/', $permalink, $postPfbid) === 1 && $postPfbid[0] === $pfbid[0];
        }
        [$pageId, $postId] = array_pad(explode('_', $post['id'] ?? '', 2), 2, '');
        preg_match_all('/\d{6,}/', $url, $numbers);
        preg_match_all('/\d{6,}/', $permalink, $postNumbers);
        $ids = array_diff([...$postNumbers[0], $postId], [$pageId]);
        return array_intersect(array_diff($numbers[0], [$pageId]), $ids) !== [];
    }

    /**
     * Where a short share link leads (Facebook redirects it to the post, or to its login page with the post as next)
     */
    private function resolve(string $url) : string
    {
        for ($i = 0; $i < 3; $i++) {
            @file_get_contents($url, false, stream_context_create(['http' => [
                'method' => 'HEAD',
                'follow_location' => 0,
                'timeout' => 10,
                'ignore_errors' => true,
            ]]));
            $location = null;
            foreach (http_get_last_response_headers() ?? [] as $header) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
            }
            if ($location === null) {
                break;
            }
            parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
            $url = is_string($query['next'] ?? null) ? $query['next'] : $location;
            if (!preg_match('#facebook\.com/share/#', $url)) {
                break;
            }
        }
        return $url;
    }

    private function get(string $url) : string
    {
        $body = @file_get_contents($url, false, stream_context_create(['http' => [
            'timeout' => 10,
            // Graph's errors come with a 400: read them too
            'ignore_errors' => true,
        ]]));
        return $body === false ? '' : $body;
    }
}
