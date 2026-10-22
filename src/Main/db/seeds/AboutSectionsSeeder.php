<?php


use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class AboutSectionsSeeder extends AbstractSeed
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

        $data = [
            [
                "title"     =>  "Le Staff Rock Fever",
                "content"   =>  $faker->text(300),
                "position"  =>  "1"
            ],
            [
                "title"     =>  "L'association UTUC",
                "content"   =>  $faker->text(300),
                "position"  =>  "2"
            ]
        ];

        $this->table('aboutSections')
            ->insert($data)
            ->save();
    }
}
