<?php

namespace App\Photos\Table;

use App\Photos\Entity\DeletionRequest;
use Framework\Database\Query;
use Framework\Database\Table;

class DeletionRequestTable extends Table
{
    protected $table = 'photo_deletion_requests';

    protected $entity = DeletionRequest::class;

    /**
     * Oldest first: the ones waiting the longest
     */
    public function findAll() : Query
    {
        return parent::findAll()->order('p.created_at ASC, p.id ASC');
    }
}
