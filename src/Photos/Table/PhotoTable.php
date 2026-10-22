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
     * Newest first
     */
    public function findAll() : Query
    {
        return parent::findAll()->order('p.created_at DESC, p.id DESC');
    }
}
