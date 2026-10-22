<?php


use Phinx\Seed\AbstractSeed;

class PhotographerSeeder extends AbstractSeed
{
    /**
     * A password for the photographers' login (made by a migration without one), to try it locally
     */
    public function run() : void
    {
        $this->execute(
            "UPDATE roles SET password = '" . password_hash('photo', PASSWORD_DEFAULT) . "' WHERE role = 'photographer'"
        );
    }
}
