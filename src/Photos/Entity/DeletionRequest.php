<?php

namespace App\Photos\Entity;

class DeletionRequest
{
    public $id;

    public $photoId;

    public $name;

    public $email;

    public $reason;

    public $createdAt;
}
