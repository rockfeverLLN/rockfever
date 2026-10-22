<?php

namespace App\Photos\Table;

use App\Photos\Entity\Photo;
use Framework\Database\Query;
use Framework\Database\Table;

class PhotoTable extends Table
{
    protected $table = 'photos';

    protected $entity = Photo::class;

    /**
     * An album's photos in the order they were uploaded (the camera's order)
     */
    public function findByAlbum(int $albumId) : Query
    {
        return $this->makeQuery()
            ->where('p.album_id = :album')
            ->params(['album' => $albumId])
            ->order('p.id ASC');
    }

    /**
     * The tabs of an album, in the order their first photo was added
     */
    public function findSections(int $albumId) : array
    {
        $query = $this->getPdo()->prepare(
            'SELECT section FROM photos WHERE album_id = ? AND section IS NOT NULL GROUP BY section ORDER BY MIN(id)'
        );
        $query->execute([$albumId]);
        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }
}
