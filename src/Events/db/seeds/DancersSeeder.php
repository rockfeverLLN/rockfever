<?php


use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class DancersSeeder extends AbstractSeed
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
        $faker = Factory::create('fr_FR');

        $data = [];
        for ($i=0; $i< 300; $i++) {
            $date = $faker->unixTime('now');
            $data[] = [
                'firstname' =>  $faker->firstName,
                'lastname'  =>  $faker->lastName,
                'event_id'  =>  rand(1, 3),
                'date'      =>  date('Y-m-d H:i:s', $date)
            ];
        }
        $this->table('dancers')
            ->insert($data)
            ->save();
    }
}
