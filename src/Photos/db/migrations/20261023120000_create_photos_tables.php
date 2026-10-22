<?php


use Phinx\Migration\AbstractMigration;

class CreatePhotosTables extends AbstractMigration
{
    /**
     * Photos of the gallery (/photos), the requests to take one down, and the gallery's password.
     * The same changes as SQL for phpMyAdmin: src/Photos/db/create-photos-tables.sql
     */
    public function change()
    {
        $this->table('photos')
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

        // Empty until an admin sets it on /admin/photos: until then, only the team sees the photos
        if ($this->isMigratingUp()) {
            $this->table('values')->insert([
                'name'  => 'PHOTOS_PASSWORD',
                'label' => 'Mot de passe de la galerie photos',
                'type'  => 'password',
                'value' => ''
            ])->save();
        }
    }
}
