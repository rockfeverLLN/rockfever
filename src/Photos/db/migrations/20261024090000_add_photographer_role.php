<?php


use Phinx\Migration\AbstractMigration;

class AddPhotographerRole extends AbstractMigration
{
    /**
     * The photographers' login, which only opens the photo albums (App\Photos\PhotographerMiddleware). Without a
     * password until an admin gives it one on /admin/roles: nobody can log in with it before.
     * The same as SQL for phpMyAdmin: src/Photos/db/add-photographer-role.sql
     */
    public function up() : void
    {
        $this->table('roles')->insert([
            'role'     => 'photographer',
            'label'    => 'Photographe',
            'password' => ''
        ])->save();
    }

    public function down() : void
    {
        $this->execute("DELETE FROM roles WHERE role = 'photographer'");
    }
}
