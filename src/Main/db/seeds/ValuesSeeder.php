<?php


use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class ValuesSeeder extends AbstractSeed
{
    /**
     * The legal page needs the ROI and TERMS texts to exist
     */
    public function run(): void
    {
        $faker = Factory::create('fr_FR');

        $this->table('values')
            ->insert([
                [
                    'name'  =>  'ROI',
                    'label' =>  'Règlement d\'ordre intérieur',
                    'type'  =>  'html',
                    'value' =>  '<p>' . $faker->text(500) . '</p>'
                ],
                [
                    'name'  =>  'TERMS',
                    'label' =>  'Conditions générales',
                    'type'  =>  'html',
                    'value' =>  '<p>' . $faker->text(500) . '</p>'
                ]
            ])
            ->save();
    }
}
