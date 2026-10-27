<?php


use Phinx\Migration\AbstractMigration;

class AddPhotoDescription extends AbstractMigration
{
    /**
     * What a photo shows, written in the album's admin page: its text alternative for screen readers, in the album
     * and the viewer. Empty until someone writes it.
     * The same as SQL for phpMyAdmin: src/Photos/db/add-photo-description.sql
     */
    public function change()
    {
        $this->table('photos')
            ->addColumn('description', 'string', ['limit' => 300, 'null' => true, 'after' => 'section'])
            ->update();
    }
}
