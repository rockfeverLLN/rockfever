<?php

namespace App\Photos;

use Framework\Validator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An album's fields from the admin's forms (new album, and edit)
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
        return $params;
    }
}
