<?php

namespace App\Photos\Table;

use App\Photos\Entity\Album;
use Framework\Database\NoRecordException;
use Framework\Database\Query;
use Framework\Database\Table;

class AlbumTable extends Table
{
    protected $table = 'photo_albums';

    protected $entity = Album::class;

    /**
     * Albums with their soirée's photo password, which opens them (PhotoAccess)
     */
    public function makeAccessQuery() : Query
    {
        return $this->makeQuery()
            ->select('p.*', 'e.photos_password AS photos_password')
            ->join('events AS e', 'e.id = p.event_id');
    }

    /**
     * @throws NoRecordException
     */
    public function findForAccess(int $id) : Album
    {
        return $this->makeAccessQuery()->where('p.id = :id')->params(['id' => $id])->fetchOrFail();
    }

    /**
     * @throws NoRecordException
     */
    public function findBySlugForAccess(string $slug) : Album
    {
        return $this->makeAccessQuery()->where('p.slug = :slug')->params(['slug' => $slug])->fetchOrFail();
    }

    /**
     * Newest first, with their number of photos and the id of the first one, for the cover
     */
    public function findAllWithCovers() : Query
    {
        return $this->makeAccessQuery()
            ->select(
                'p.*',
                'e.photos_password AS photos_password',
                '(SELECT COUNT(*) FROM photos WHERE album_id = p.id) AS photo_count',
                '(SELECT id FROM photos WHERE album_id = p.id ORDER BY id LIMIT 1) AS cover_id'
            )
            ->order('p.created_at DESC, p.id DESC');
    }

    /**
     * The soirées an album can belong to, newest first, with whether they have a photo password
     * @return object[] id, title, date, photos_password
     */
    public function findEvents() : array
    {
        return $this->getPdo()
            ->query('SELECT id, title, date, photos_password FROM events ORDER BY date DESC, id DESC')
            ->fetchAll(\PDO::FETCH_OBJ);
    }

    public function eventExists(int $id) : bool
    {
        $query = $this->getPdo()->prepare('SELECT id FROM events WHERE id = ?');
        $query->execute([$id]);
        return $query->fetchColumn() !== false;
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
