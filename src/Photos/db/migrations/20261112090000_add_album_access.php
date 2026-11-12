<?php


use Phinx\Migration\AbstractMigration;

class AddAlbumAccess extends AbstractMigration
{
    /**
     * Each album's own password, set on its admin page (photographers too). Empty: the album is public.
     * Albums take their soirée's photo password, which opened them until now, so none opens up by itself.
     * The same as SQL for phpMyAdmin: src/Photos/db/add-album-access.sql
     */
    public function up() : void
    {
        $this->table('photo_albums')
            ->addColumn('password', 'string', ['limit' => 100, 'null' => true, 'after' => 'event_id'])
            ->update();
        $this->execute(
            "UPDATE photo_albums a JOIN events e ON e.id = a.event_id SET a.password = e.photos_password
            WHERE e.photos_password <> ''"
        );
    }

    public function down() : void
    {
        $this->table('photo_albums')->removeColumn('password')->update();
    }
}
