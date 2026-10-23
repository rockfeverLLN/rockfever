<?php


use Phinx\Migration\AbstractMigration;

class CreatePhotosTables extends AbstractMigration
{
    /**
     * Albums and photos of the gallery (/photos), the requests to take one down, and each soirée's photo password.
     * The same changes as SQL for phpMyAdmin: src/Photos/db/create-photos-tables.sql
     */
    public function change()
    {
        // Given to the soirée's dancers: it opens the soirée's album. Kept readable, so the team can find it again
        $this->table('events')
            ->addColumn('photos_password', 'string', ['limit' => 100, 'null' => true])
            ->update();

        // An album is a soirée ("Semaine 5 26-27"), in a collection (the quadrimestre: "Q1-26-27")
        $this->table('photo_albums')
            ->addColumn('title', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 120])
            ->addIndex('slug', ['unique' => true])
            ->addColumn('collection', 'string', ['limit' => 100])
            ->addColumn('photographer', 'string', ['limit' => 100, 'null' => true])
            // Its soirée, whose photo password opens it. Without one, only the team sees the album
            ->addColumn('event_id', 'integer', ['null' => true])
            ->addForeignKey('event_id', 'events', ['id'], ['delete' => 'SET_NULL'])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        $this->table('photos')
            ->addColumn('album_id', 'integer')
            ->addForeignKey('album_id', 'photo_albums', ['id'], ['delete' => 'CASCADE'])
            // The album's tab the photo is in ("Photobooth"), none for "Toutes les photos" only
            ->addColumn('section', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('filename', 'string', ['limit' => 64])
            ->addColumn('width', 'integer')
            ->addColumn('height', 'integer')
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        $this->table('photo_deletion_requests')
            ->addColumn('photo_id', 'integer')
            ->addForeignKey('photo_id', 'photos', ['id'], ['delete' => 'CASCADE'])
            ->addColumn('name', 'string')
            ->addColumn('email', 'string', ['null' => true])
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
