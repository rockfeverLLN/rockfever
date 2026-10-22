<?php


use Phinx\Seed\AbstractSeed;

class RolesSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run(): void
    {
        $this->table('roles')
            ->insert([
                'label'  =>  'Administrateur',
                'role'     =>  'administrator',
                'password'  =>  password_hash('admin', PASSWORD_DEFAULT)
            ])
            ->insert([
                'label'  =>  'Modérateur',
                'role'     =>  'moderator',
                'password'  =>  password_hash('modo', PASSWORD_DEFAULT)
            ])
            ->save();
    }
}
