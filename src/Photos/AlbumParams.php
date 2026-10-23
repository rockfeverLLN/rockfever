<?php

namespace App\Photos;

use Framework\Validator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An album's fields from the admin's forms (new album, and edit). The soirée (event_id) is checked by the action
 */
class AlbumParams
{
    /**
     * @return array|null The fields, or null if the title or the collection is missing
     */
    public static function fromRequest(ServerRequestInterface $request) : ?array
    {
        $body = $request->getParsedBody() ?? [];
        $params = [];
        foreach (['title', 'collection', 'photographer'] as $key) {
            $params[$key] = is_string($body[$key] ?? null) ? trim($body[$key]) : '';
        }
        $validator = (new Validator($params))
            ->notEmpty('title', 'collection')
            ->length('title', null, 100)
            ->length('collection', null, 100)
            ->length('photographer', null, 100);
        if (!$validator->isValid()) {
            return null;
        }
        $params['photographer'] = $params['photographer'] ?: null;
        $event = $body['event_id'] ?? '';
        $params['event_id'] = is_string($event) && ctype_digit($event) ? (int) $event : null;
        return $params;
    }
}
