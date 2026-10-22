<?php


use Phinx\Seed\AbstractSeed;

class EventsSeeder extends AbstractSeed
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
        $data = [
            [
                "title"     =>  "The Valentin's Carnaval",
                "description"=> "The Valentin's Carnaval
                                    Vous en rêviez...
                                    Vous le vouliez...
                                    Vous l'attendiez...
                                    le retour du Rock Fever est arrivé!!
                                    Et croyez moi, on va fêter ca comme il se doit!
                                    Puisque c'est la saint Valentin et le Carnaval, on va fêter les deux!
                                    Alors réveillez le prince charmant, la princesse, le cupidon, le dragon, 
                                    le pirate, le bidule, ou le machin qui sommeille en vous et 
                                    revêtez votre plus incroyable déguisement!
                                    Car durant la soirée, notre impartial jury, le célèbre 
                                    \"staff RockFever\", élira le/la meilleur(e) costumé(e), et lui 
                                    remettra un humble présent!
                                    Ne tardez plus, on vous attend nombreux et remontés 
                                    comme des ressorts pour passer le plus dansant des 
                                    carnavals de Saint-Valentin!",
                "date"      =>  "2018-02-14",
                "waltz_time"=>  "20:30:00",
                "rock_time" =>  "21:00:00",
                "place"     =>  "Hall Sainte Barbe",
                "price"     =>  "3€ (2€ si inscription)",
                "facebook_link"=>"https://www.facebook.com/events/280706045792502/"
            ],
            [
                "title"     =>  "Rockambolesque",
                "description"=> "Amis rockeurs et amies rockeuses !
                                    Après deux semaine d'absence et une attente agonisante 
                                    le Rock Fever sort de sa latence !
                                    Par cette froide période de fin d'hiver, il est temps 
                                    de ranger au placard écharpes, bonnets et gants et de 
                                    partir vivre une aventure rockambolesquement géniale 
                                    pour réchauffer vos petits pieds engourdis !
                                    Venez partager votre folie, votre soif de danse et 
                                    fièvre du dancefloor ce mercredi 7 mars dans notre 
                                    temple du rock, le hall Sainte-Barbe.",
                "date"      =>  "2018-03-07",
                "waltz_time"=>  "20:30:00",
                "rock_time" =>  "21:00:00",
                "place"     =>  "Hall Sainte Barbe",
                "price"     =>  "3€ (2€ si inscription)",
                "facebook_link"=>"https://www.facebook.com/events/185321708739996/"
            ],
            [
                "title"     =>  "Spring Rocks",
                "description"=> "Les oiseaux chantent, les rayons de soleil percent à 
                travers les rideaux,... C'est indéniable, le Printemps est de retour ! 
                C'est l'occasion idéal pour aller se trémousser le temps d'une soirée ! 
                Venez rendre hommage à cette sublime saison en dansant et discutant à la 
                soirée Rock Fever !",
                "date"      =>  "2018-03-21",
                "waltz_time"=>  "20:30:00",
                "rock_time" =>  "21:00:00",
                "place"     =>  "Hall Sainte Barbe",
                "price"     =>  "3€ (2€ si inscription)",
                "facebook_link"=>"https://www.facebook.com/events/393471884450237/"
            ]
        ];

        $this->table('events')
            ->insert($data)
            ->save();
    }
}
