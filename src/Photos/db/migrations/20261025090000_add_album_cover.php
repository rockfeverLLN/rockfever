<?php


use Phinx\Migration\AbstractMigration;

class AddAlbumCover extends AbstractMigration
{
    /**
     * The photo an admin chose as an album's cover. No foreign key: when that photo is deleted, the album simply
     * falls back to its first photo (AlbumTable::findAllWithCovers).
     * The same as SQL for phpMyAdmin: src/Photos/db/add-album-cover.sql
     */
    public function change()
    {
        $this->table('photo_albums')
            ->addColumn('cover_photo_id', 'integer', ['null' => true, 'after' => 'event_id'])
            ->update();
    }
}
