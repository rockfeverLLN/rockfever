<?php

namespace App\Photos\Table;

use App\Photos\Entity\Album;
use Framework\Database\Query;
use Framework\Database\Table;

class AlbumTable extends Table
{
    protected $table = 'photo_albums';

    protected $entity = Album::class;

    /**
     * Newest first, with their number of photos and the id of the first one, for the cover
     */
    public function findAllWithCovers() : Query
    {
        return $this->makeQuery()
            ->select(
                'p.*',
                '(SELECT COUNT(*) FROM photos WHERE album_id = p.id) AS photo_count',
                '(SELECT id FROM photos WHERE album_id = p.id ORDER BY id LIMIT 1) AS cover_id'
            )
            ->order('p.created_at DESC, p.id DESC');
    }

    /**
     * Collections already used, to suggest them when making an album
     */
    public function findCollections() : array
    {
        return $this->getPdo()
            ->query('SELECT collection FROM photo_albums GROUP BY collection ORDER BY MAX(id) DESC')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findPhotographers() : array
    {
        return $this->getPdo()
            ->query('SELECT photographer FROM photo_albums WHERE photographer IS NOT NULL GROUP BY photographer ORDER BY MAX(id) DESC')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * A slug no other album has: "semaine-5", then "semaine-5-2"...
     */
    public function makeSlug(string $title, ?int $exclude = null) : string
    {
        $base = Album::slugify($title);
        $slug = $base;
        for ($i = 2; $this->entryExists('slug', $slug, $exclude); $i++) {
            $slug = "$base-$i";
        }
        return $slug;
    }
}
