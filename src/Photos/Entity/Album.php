<?php

namespace App\Photos\Entity;

class Album
{
    public $id;

    public $title;

    public $slug;

    public $collection;

    public $photographer;

    public $eventId;

    /**
     * The password that opens it (PhotoAccess). Empty: everyone sees it
     */
    public $password;

    /**
     * The photo chosen as cover, if any: coverId is the one to show (AlbumTable::findAllWithCovers)
     */
    public $coverPhotoId;

    public $createdAt;

    /**
     * Number of photos, the cover's id and the soirée's date, when the query asks for them
     * (AlbumTable::findAllWithCovers)
     */
    public $photoCount = 0;

    public $coverId;

    public $eventDate;

    /**
     * Anchor of the album's collection on /photos, for the breadcrumb
     */
    public function getCollectionSlug() : string
    {
        return self::slugify((string) $this->collection);
    }

    /**
     * "Semaine 5 26-27" => "semaine-5-26-27"
     */
    public static function slugify(string $text) : string
    {
        $text = strtr(mb_strtolower(trim($text)), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae'
        ]);
        return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-') ?: 'album';
    }
}
