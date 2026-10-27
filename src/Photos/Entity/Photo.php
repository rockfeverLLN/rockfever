<?php

namespace App\Photos\Entity;

class Photo
{
    public $id;

    public $albumId;

    public $section;

    /**
     * What the photo shows, for screen readers. Null until someone writes it in the admin
     */
    public $description;

    public $filename;

    public $width;

    public $height;

    public $createdAt;
}
