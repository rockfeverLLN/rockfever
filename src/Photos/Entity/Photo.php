<?php

namespace App\Photos\Entity;

class Photo
{
    public $id;

    public $filename;

    public $width;

    public $height;

    public $createdAt;

    /**
     * Width over height, for the grid to lay the photo out before it loads
     */
    public function getRatio() : float
    {
        return $this->height ? round($this->width / $this->height, 4) : 1;
    }
}
