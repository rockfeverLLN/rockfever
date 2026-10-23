<?php
namespace App\Main\Actions;

use App\Events\Entity\Event;
use App\Main\Calendar;
use GuzzleHttp\Psr7\Response;

/**
 * /api/events: every soirée as JSON, oldest first, past and to come, with its sign-up, for programs and AI agents.
 * The same data as the pages, from Calendar
 */
class SoireesApiAction
{
    public function __construct(private Calendar $calendar)
    {
    }

    public function __invoke()
    {
        $next = $this->calendar->getNext();
        $data = [
            'organizer' => [
                'name' => 'Rock Fever',
                'url' => Calendar::SITE . '/',
                'contact' => Calendar::SITE . '/contact',
            ],
            'generatedAt' => (new \DateTimeImmutable('now', new \DateTimeZone(Calendar::TIMEZONE)))->format('c'),
            'timezone' => Calendar::TIMEZONE,
            'next' => $next ? $next['date'] : null,
            'registration' => [
                'about' => 'Seule la prochaine soirée peut avoir ses inscriptions ouvertes. Elles ouvrent '
                    . Event::REGISTRATION_DAYS . ' jours avant à minuit (pas avant la fin de la soirée précédente) '
                    . 'et ferment au début de la soirée. On paie à l\'entrée, inscrit ou non.',
                'statuses' => [
                    Calendar::REGISTRATION_OPEN => 'ouvertes : le formulaire de /event est là',
                    Calendar::REGISTRATION_UPCOMING => 'pas encore ouvertes : elles ouvrent à registration.opens',
                    Calendar::REGISTRATION_PENDING => 'pas encore ouvertes : la date est passée, l\'équipe doit encore publier la soirée',
                    Calendar::REGISTRATION_CLOSED => 'fermées : fermées par l\'équipe, ou la soirée a commencé',
                    Calendar::REGISTRATION_CANCELLED => 'la soirée est annulée',
                ],
                'form' => [
                    'url' => Calendar::SITE . '/event',
                    'method' => 'POST',
                    'contentType' => 'application/x-www-form-urlencoded',
                    'fields' => [
                        ['name' => 'firstname', 'label' => 'Prénom', 'required' => true],
                        ['name' => 'lastname', 'label' => 'Nom', 'required' => true],
                    ],
                    'terms' => Calendar::SITE . '/legal',
                    'humanConsent' => 'L\'inscription met une vraie personne sur la liste d\'entrée et vaut acceptation du R.O.I. : '
                        . 'ne l\'envoie que si cette personne te l\'a demandé, avec son propre nom. Voir ' . Calendar::SITE . '/AGENTS.md',
                ],
            ],
            'events' => array_map([$this->calendar, 'toApi'], $this->calendar->all()),
        ];
        return new Response(200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            // Public data: any site or tool may read it
            'Access-Control-Allow-Origin' => '*',
            'Link' => '<' . Calendar::SITE . '/calendrier>; rel="alternate"; type="text/html"',
        ], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
