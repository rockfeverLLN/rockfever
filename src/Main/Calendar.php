<?php
namespace App\Main;

use App\Events\Entity\Event;
use App\Events\Table\EventTable;
use App\Events\Table\SoireeTable;

/**
 * The soirées, each one put together in one place: its date and its event, both planned in the admin (/admin/events),
 * matched by day. The home page, /calendrier, /event and its sign-up form, the .ics files, the schema.org markup,
 * the sitemap and /api/events all read them from here, so they always give the same answer
 */
class Calendar
{
    const TIMEZONE = 'Europe/Brussels';

    const TITLE = 'Soirée Rock Fever';

    const SITE = 'https://rockfever.be';

    // Where a soirée's sign-up stands (registration.status)
    const REGISTRATION_OPEN = 'open';
    // Opens on its own at registration.opens
    const REGISTRATION_UPCOMING = 'upcoming';
    // The opening day is reached, but the team hasn't created the soirée's event in the admin yet
    const REGISTRATION_PENDING = 'pending';
    // Closed in the admin, or the soirée has started
    const REGISTRATION_CLOSED = 'closed';
    const REGISTRATION_CANCELLED = 'cancelled';

    private const MONTHS = [
        'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
    ];

    private const SHORT_MONTHS = [
        'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
        'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'
    ];

    private const WEEKDAYS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    /**
     * @var array[]|null the dates of the admin, oldest first, once read
     */
    private $dates;

    /**
     * @var array[]|null every soirée at the time of the request, once built
     */
    private $all;

    private string $start;
    private string $rock;
    private string $end;
    private string $place;
    private string $address;
    /** @var array{online: int, door: int} */
    private array $prices;
    private int $registrationDays;
    private string $openTime;
    private int $closeDays;
    // Empty: sign-ups close when the soirée starts
    private string $closeTime;

    /**
     * The usual times, place, prices and sign-up window: the settings of /admin/events (App\Main\Settings)
     */
    public function __construct(private SoireeTable $soirees, private EventTable $events, Settings $settings)
    {
        $this->start = $settings->get('SOIREE_WALTZ_TIME');
        $this->rock = $settings->get('SOIREE_ROCK_TIME');
        $this->end = $settings->get('SOIREE_END_TIME');
        $this->place = $settings->get('SOIREE_PLACE');
        $this->address = $settings->get('SOIREE_ADDRESS');
        $this->prices = ['online' => $settings->getInt('PRICE_ONLINE'), 'door' => $settings->getInt('PRICE_DOOR')];
        $this->registrationDays = $settings->getInt('REGISTRATION_DAYS');
        $this->openTime = $settings->get('REGISTRATION_OPEN_TIME');
        $this->closeDays = $settings->getInt('REGISTRATION_CLOSE_DAYS');
        $this->closeTime = $settings->get('REGISTRATION_CLOSE_TIME');
    }

    /**
     * The dates planned in the admin, oldest first
     * @return array[]
     */
    private function dates() : array
    {
        return $this->dates ??= $this->soirees->findDates();
    }

    /**
     * Every soirée, oldest first: the dates and the events of the admin (back to the first one)
     * @return array[]
     */
    public function all(?\DateTimeImmutable $now = null) : array
    {
        if ($now === null && $this->all !== null) {
            return $this->all;
        }
        $listed = array_column($this->dates(), null, 'date');
        $events = [];
        foreach ($this->events->findAllByDay() as $event) {
            $events[$event->getDay()] ??= $event;
        }
        $days = array_unique(array_merge(array_keys($listed), array_keys($events)));
        sort($days);

        $soirees = [];
        $previousEnd = null;
        $nextFound = false;
        foreach ($days as $day) {
            $soiree = $this->build($day, $listed[$day] ?? null, $events[$day] ?? null, $now ?? self::now(), $previousEnd);
            $soiree['next'] = !$soiree['past'] && !$soiree['cancelled'] && !$nextFound;
            $nextFound = $nextFound || $soiree['next'];
            if (!$soiree['cancelled']) {
                $previousEnd = $soiree['end'];
            }
            $soirees[] = $soiree;
        }
        if ($now === null) {
            $this->all = $soirees;
        }
        return $soirees;
    }

    /**
     * The soirées of /calendrier and the home page: the planned dates, and the admin's events from the first of them on
     * (or still to come)
     * @return array[]
     */
    public function getSoirees(?\DateTimeImmutable $now = null) : array
    {
        $first = $this->dates()[0]['date'] ?? null;
        return array_values(array_filter(
            $this->all($now),
            fn (array $soiree) => $soiree['listed'] || !$soiree['past'] || ($first !== null && $soiree['date'] >= $first)
        ));
    }

    /**
     * When sign-ups open for a soirée on that day (Y-m-d), planned or not, as build() works it out: the days of the settings
     * before, but not before the soirée before it is over
     */
    public function getRegistrationOpening(string $day) : \DateTimeImmutable
    {
        $opens = $this->opening($day);
        foreach ($this->all() as $soiree) {
            if ($soiree['date'] >= $day) {
                break;
            }
            if (!$soiree['cancelled'] && $soiree['end'] > $opens) {
                $opens = $soiree['end'];
            }
        }
        return $opens;
    }

    /**
     * When each soirée not cancelled ends (day Y-m-d => c), for the event form to work out the opening of another date
     * @return string[]
     */
    public function getEnds() : array
    {
        $ends = [];
        foreach ($this->all() as $soiree) {
            if (!$soiree['cancelled']) {
                $ends[$soiree['date']] = $soiree['end']->format('c');
            }
        }
        return $ends;
    }

    /**
     * The next soirée (or the one under way), not cancelled: the one of /event, and the only one whose sign-up can be open
     */
    public function getNext(?\DateTimeImmutable $now = null) : ?array
    {
        foreach ($this->all($now) as $soiree) {
            if ($soiree['next']) {
                return $soiree;
            }
        }
        return null;
    }

    /**
     * The soirée of that day (Y-m-d)
     */
    public function find(string $date, ?\DateTimeImmutable $now = null) : ?array
    {
        foreach ($this->all($now) as $soiree) {
            if ($soiree['date'] === $date) {
                return $soiree;
            }
        }
        return null;
    }

    /**
     * "mercredi" for 3 (0 is Sunday, as date('w'))
     */
    public static function weekdayName(int $weekday) : string
    {
        return mb_strtolower(self::WEEKDAYS[$weekday]);
    }

    /**
     * "Mercredi 11 novembre", "Mercredi 1er octobre"
     */
    public static function label(\DateTimeInterface $date) : string
    {
        return self::WEEKDAYS[$date->format('w')] . ' ' . ($date->format('j') === '1' ? '1er' : $date->format('j'))
            . ' ' . self::MONTHS[$date->format('n') - 1];
    }

    /**
     * Groups the soirées by quadrimestre: "Septembre – Décembre 2026", "Février – Mai 2027"
     * @return array[] label => soirées
     */
    public function getSemesters(?\DateTimeImmutable $now = null) : array
    {
        $semesters = [];
        foreach ($this->getSoirees($now) as $soiree) {
            $month = (int) $soiree['start']->format('n');
            $label = $month >= 8 ? 'Septembre – Décembre ' . $soiree['year'] : 'Février – Mai ' . $soiree['year'];
            $semesters[$label][] = $soiree;
        }
        return $semesters;
    }

    /**
     * The quadrimestre after the last listed soirée, announced on the page until its dates are added
     */
    public function getNextSemester() : ?string
    {
        $dates = $this->dates();
        $last = end($dates);
        if (!$last) {
            return null;
        }
        [$year, $month] = array_map('intval', explode('-', $last['date']));
        return $month >= 8 ? 'Février – Mai ' . ($year + 1) : 'Septembre – Décembre ' . $year;
    }

    public function getPlace() : string
    {
        return $this->place;
    }

    public function getAddress() : string
    {
        return $this->address;
    }

    /**
     * ['online' => 3, 'door' => 4]: in euros, paid at the door, with or without signing up on the site
     */
    public function getPrices() : array
    {
        return $this->prices;
    }

    /**
     * The sign-up window of the settings, for the event form's script: ['days' => 7, 'openTime' => '00:00',
     * 'closeDays' => 0, 'closeTime' => ''] (empty: when the soirée starts)
     * @return array{days: int, openTime: string, closeDays: int, closeTime: string}
     */
    public function getRegistrationWindow() : array
    {
        return [
            'days' => $this->registrationDays,
            'openTime' => $this->openTime,
            'closeDays' => $this->closeDays,
            'closeTime' => $this->closeTime,
        ];
    }

    /**
     * The weekday of the soirées (0 for Sunday), from the next one, or the last one: "7 jours avant" is then "le
     * mercredi" in the settings. Null without any soirée
     */
    public function getUsualWeekday() : ?int
    {
        $soirees = array_values(array_filter($this->all(), fn (array $soiree) => !$soiree['cancelled']));
        $next = array_values(array_filter($soirees, fn (array $soiree) => !$soiree['past']))[0] ?? end($soirees);
        return $next ? (int) $next['start']->format('w') : null;
    }

    /**
     * The sign-up window in words: "7 jours avant à minuit, jusqu’au début de la soirée"
     */
    public function getRegistrationRule() : string
    {
        $at = fn (string $time) => $time === '00:00' ? 'minuit' : self::hour($time);
        $opens = match ($this->registrationDays) {
            0 => 'le jour même',
            1 => 'la veille',
            default => $this->registrationDays . ' jours avant',
        } . ' à ' . $at($this->openTime);
        if ($this->closeTime === '' && $this->closeDays === 0) {
            return $opens . ', jusqu’au début de la soirée';
        }
        return $opens . ', ' . match ($this->closeDays) {
            0 => 'jusqu’au jour de la soirée',
            1 => 'jusqu’à la veille',
            default => 'jusqu’à ' . $this->closeDays . ' jours avant',
        } . ' à ' . ($this->closeTime === '' ? 'l’heure du début' : $at($this->closeTime));
    }

    /**
     * When sign-ups open for a soirée that day (Y-m-d), before the previous soirée's end is taken into account
     */
    private function opening(string $day) : \DateTimeImmutable
    {
        [$hours, $minutes] = array_map('intval', explode(':', $this->openTime));
        return Event::registrationOpeningFor($day, $this->registrationDays)->setTime($hours, $minutes);
    }

    /**
     * When sign-ups close for a soirée from $start to $end: the time of the settings that many days before, or when it
     * starts; never after it's over
     */
    private function closing(\DateTimeImmutable $start, \DateTimeImmutable $end) : \DateTimeImmutable
    {
        $closes = $start->modify('-' . $this->closeDays . ' days');
        if ($this->closeTime !== '') {
            [$hours, $minutes] = array_map('intval', explode(':', $this->closeTime));
            $closes = $closes->setTime($hours, $minutes);
        }
        return min($closes, $end);
    }

    /**
     * Until when a soirée that day (Y-m-d), starting at that time (H:i), takes sign-ups, in words (until())
     */
    public function getRegistrationUntil(string $day, string $startTime) : string
    {
        [$start, $end] = $this->getTimes($day, $startTime);
        return self::until($this->closing($start, $end), $start);
    }

    public function getEndTime() : string
    {
        return $this->end;
    }

    /**
     * "jusqu’au début de la soirée", or "jusqu’au mardi 10 novembre à 18h"
     */
    public static function until(\DateTimeImmutable $closes, \DateTimeImmutable $start) : string
    {
        return $closes == $start ? 'jusqu’au début de la soirée' : 'jusqu’au ' . self::dayTimeLabel($closes);
    }

    /**
     * "mercredi 4 novembre", "vendredi 6 novembre à 12h" (the time unless midnight)
     */
    public static function dayTimeLabel(\DateTimeInterface $date) : string
    {
        $time = $date->format('H:i');
        return mb_strtolower(self::label($date)) . ($time === '00:00' ? '' : ' à ' . self::hour($time));
    }

    public function has(string $date) : bool
    {
        return $this->find($date) !== null;
    }

    /**
     * iCalendar file with the given soirée (those of /calendrier when $date is null)
     */
    public function toIcs(?string $date = null) : string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Rock Fever//Calendrier//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Rock Fever',
            'X-WR-TIMEZONE:' . self::TIMEZONE,
            // Calendars that subscribe to the file check it again twice a day
            'REFRESH-INTERVAL;VALUE=DURATION:PT12H',
            'X-PUBLISHED-TTL:PT12H',
        ];
        $stamp = gmdate('Ymd\THis\Z');
        foreach ($date === null ? $this->getSoirees() : array_filter([$this->find($date)]) as $soiree) {
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:soiree-' . $soiree['date'] . '@rockfever.be',
                'DTSTAMP:' . $stamp,
                'DTSTART:' . self::utc($soiree['start']),
                'DTEND:' . self::utc($soiree['end']),
                'SUMMARY:' . self::escape($soiree['name']),
                'LOCATION:' . self::escape(self::location($soiree)),
                'DESCRIPTION:' . self::escape(self::description($soiree)),
                'URL:' . $soiree['url'],
                'STATUS:' . ($soiree['cancelled'] ? 'CANCELLED' : 'CONFIRMED'),
                'END:VEVENT',
            ]);
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /**
     * The soirée as a schema.org DanceEvent, for search engines and AI assistants
     */
    public function toSchema(array $soiree) : array
    {
        $registration = $soiree['registration'];
        $rockFever = ['@type' => 'Organization', '@id' => self::SITE . '/#organization', 'name' => 'Rock Fever', 'url' => self::SITE . '/'];
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'DanceEvent',
            '@id' => $soiree['url'] . '#event',
            'name' => $soiree['name'],
            'description' => self::description($soiree, false),
            'url' => $soiree['url'],
            'startDate' => $soiree['start']->format('c'),
            'endDate' => $soiree['end']->format('c'),
            'eventStatus' => 'https://schema.org/' . ($soiree['cancelled'] ? 'EventCancelled' : 'EventScheduled'),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => ['@type' => 'Place', 'name' => $soiree['place']]
                + ($soiree['address'] ? ['address' => self::postalAddress($soiree['address'])] : []),
            'image' => self::SITE . '/images/body/bg.webp',
            'inLanguage' => 'fr',
            'typicalAgeRange' => '18-35',
            'isAccessibleForFree' => false,
            'organizer' => $rockFever,
            // No band or DJ: the team plays the music, from the songs proposed on /music
            'performer' => $rockFever,
        ];
        // A past soirée keeps its offers, sold out: Google flags events without them, past pages included
        if ($soiree['prices']) {
            $schema['offers'] = [
                [
                    '@type' => 'Offer',
                    'name' => 'Inscrit sur le site (payé à l’entrée)',
                    'price' => $soiree['prices']['online'],
                    'priceCurrency' => 'EUR',
                    'url' => self::SITE . '/event',
                    'availability' => 'https://schema.org/' . ($registration['status'] === self::REGISTRATION_OPEN ? 'InStock' : 'OutOfStock'),
                    'validFrom' => $registration['opens']->format('c'),
                    'validThrough' => $registration['closes']->format('c'),
                ],
                [
                    '@type' => 'Offer',
                    'name' => 'Sur place',
                    'price' => $soiree['prices']['door'],
                    'priceCurrency' => 'EUR',
                    'url' => $soiree['url'],
                    'availability' => 'https://schema.org/' . ($soiree['cancelled'] || $soiree['past'] ? 'OutOfStock' : 'InStock'),
                    // Paid at the door: from the start of the soirée to its end
                    'validFrom' => $soiree['start']->format('c'),
                    'validThrough' => $soiree['end']->format('c'),
                ],
            ];
        }
        return $schema;
    }

    /**
     * The soirée for /api/events
     */
    public function toApi(array $soiree) : array
    {
        $registration = $soiree['registration'];
        return [
            'id' => $soiree['date'],
            'url' => $soiree['url'],
            'name' => $soiree['name'],
            'summary' => $soiree['summary'],
            'theme' => $soiree['title'],
            'themeNote' => $soiree['themeNote'],
            'description' => self::description($soiree, false),
            'startDate' => $soiree['start']->format('c'),
            'endDate' => $soiree['end']->format('c'),
            'timezone' => self::TIMEZONE,
            'waltzTime' => $soiree['waltzTime'],
            'rockTime' => $soiree['rockTime'],
            'status' => $soiree['cancelled'] ? 'cancelled' : 'scheduled',
            'past' => $soiree['past'],
            'next' => $soiree['next'],
            'week' => $soiree['week'],
            'location' => ['name' => $soiree['place'], 'address' => $soiree['address']],
            'ageRange' => '18-35',
            'requirements' => 'Carte d’identité ou d’étudiant obligatoire à l’entrée.',
            'prices' => $soiree['prices']
                ? [
                    'currency' => 'EUR',
                    'registered' => $soiree['prices']['online'],
                    'door' => $soiree['prices']['door'],
                    'paidAt' => 'door',
                ]
                : null,
            'priceText' => $soiree['prices'] ? null : $soiree['priceText'],
            'registration' => [
                'status' => $registration['status'],
                'opens' => $registration['opens']->format('c'),
                'closes' => $registration['closes']->format('c'),
                'url' => $registration['status'] === self::REGISTRATION_OPEN ? self::SITE . '/event' : null,
            ],
            'links' => array_filter([
                'ics' => self::SITE . '/calendrier/' . $soiree['date'] . '.ics',
                'googleCalendar' => $soiree['past'] || $soiree['cancelled'] ? null : $soiree['googleUrl'],
                'facebook' => $soiree['facebookLink'] ?: null,
            ]),
        ];
    }

    /**
     * What an assistant can offer to do next for the next soirée: add it to a calendar, be reminded when its sign-ups
     * open (or before it starts, once they are open), follow all the dates
     */
    public function toSuggestions(array $soiree) : array
    {
        $registration = $soiree['registration'];
        $suggestions = [[
            'type' => 'addToCalendar',
            'label' => 'Ajouter la soirée du ' . lcfirst($soiree['label']) . ' à mon calendrier',
            'ics' => self::SITE . '/calendrier/' . $soiree['date'] . '.ics',
            'googleCalendar' => $soiree['googleUrl'],
        ]];
        if (in_array($registration['status'], [self::REGISTRATION_UPCOMING, self::REGISTRATION_PENDING], true)) {
            // Pending: the opening day is reached, they open as soon as the team publishes the soirée
            $upcoming = $registration['status'] === self::REGISTRATION_UPCOMING;
            $suggestions[] = [
                'type' => 'reminder',
                'label' => $upcoming
                    ? 'Me prévenir quand les inscriptions ouvrent, le ' . lcfirst(self::label($registration['opens']))
                    : 'Me prévenir dès que les inscriptions ouvrent (très bientôt)',
                'at' => $upcoming ? $registration['opens']->format('c') : null,
                'then' => 'Vérifier registration.status sur ' . self::SITE . '/api/events : s’il vaut open, proposer de s’inscrire sur '
                    . self::SITE . '/event' . ($soiree['prices'] ? ' (' . $soiree['prices']['online'] . ' € au lieu de ' . $soiree['prices']['door'] . ' €)' : '')
                    . ' ; s’il vaut pending, revérifier plus tard le même jour.',
            ];
        } elseif ($registration['status'] === self::REGISTRATION_OPEN) {
            $suggestions[] = [
                'type' => 'reminder',
                'label' => 'Me rappeler de m’inscrire avant la fermeture des inscriptions',
                'at' => $registration['closes']->modify('-1 day')->format('c'),
                'then' => 'Proposer de s’inscrire sur ' . self::SITE . '/event avant ' . $registration['closes']->format('c') . '.',
            ];
        }
        $suggestions[] = [
            'type' => 'subscribe',
            'label' => 'Suivre toutes les dates du Rock Fever dans mon calendrier',
            'webcal' => 'webcal://rockfever.be/calendrier.ics',
            'ics' => self::SITE . '/calendrier.ics',
        ];
        return $suggestions;
    }

    /**
     * One soirée, from its planned date ($listed) and its event, either of which can be missing
     */
    private function build(
        string $day,
        ?array $listed,
        ?Event $event,
        \DateTimeImmutable $now,
        ?\DateTimeImmutable $previousEnd
    ) : array {
        // The times of the admin's event, or the usual ones, so that every soirée says when the waltz and the rock start
        $waltzTime = $event?->waltzTime ?: null;
        $rockTime = $event?->rockTime ?: null;
        if ($waltzTime === null && $rockTime === null) {
            [$waltzTime, $rockTime] = [$this->start, $this->rock];
        }
        [$start, $end] = $this->getTimes($day, $waltzTime ?? $rockTime);
        $cancelled = !empty($listed['cancelled']);
        $past = $end <= $now;

        // A place typed in the admin other than the usual one (whatever its spelling) moves the soirée there
        $place = $this->place;
        $address = $this->address;
        if ($event && trim((string) $event->place) !== '' && self::simplify($event->place) !== self::simplify($this->place)) {
            $place = trim($event->place);
            $address = null;
        }

        // Sign-ups open and close as the settings say, but don't open before the previous soirée is over: only the next
        // soirée can have them open
        $opens = $this->opening($day);
        if ($previousEnd !== null && $previousEnd > $opens) {
            $opens = $previousEnd;
        }
        $closes = $this->closing($start, $end);
        if ($cancelled) {
            $status = self::REGISTRATION_CANCELLED;
        } elseif (($event && !empty($event->closed)) || $now >= $closes) {
            $status = self::REGISTRATION_CLOSED;
        } elseif ($now < $opens) {
            $status = self::REGISTRATION_UPCOMING;
        } elseif (!$event) {
            $status = self::REGISTRATION_PENDING;
        } else {
            $status = self::REGISTRATION_OPEN;
        }

        $title = $event ? trim((string) $event->title) : '';
        $soiree = [
            'date' => $day,
            'url' => self::SITE . '/calendrier/' . $day,
            'listed' => $listed !== null,
            'week' => $listed['week'] ?? null,
            // Its row of the soirees table, for the admin
            'soireeId' => $listed['id'] ?? null,
            'event' => $event,
            'title' => $title ?: null,
            'name' => $title ?: self::TITLE,
            'description' => $event ? self::paragraphs((string) $event->description) : null,
            'start' => $start,
            'end' => $end,
            'waltzTime' => $waltzTime,
            'rockTime' => $rockTime,
            'place' => $place,
            'address' => $address,
            // The prices of the config are those of the planned soirées and those to come; older events only have the
            // admin's text
            'prices' => $listed !== null || !$past ? $this->prices : null,
            'priceText' => $event ? (trim((string) $event->price) ?: null) : null,
            'facebookLink' => $event?->facebookLink ?: null,
            'cancelled' => $cancelled,
            'past' => $past,
            'registration' => [
                'status' => $status,
                'opens' => $opens,
                'closes' => $closes,
                // "jusqu’au début de la soirée", or "jusqu’au mardi 10 novembre à 18h"
                'until' => self::until($closes, $start),
            ],
            'day' => $start->format('j'),
            'weekday' => self::WEEKDAYS[$start->format('w')],
            'month' => self::MONTHS[$start->format('n') - 1],
            'shortMonth' => self::SHORT_MONTHS[$start->format('n') - 1],
            'year' => $start->format('Y'),
            'label' => self::label($start),
        ];
        // Its theme (the admin's title) comes with its event, which the team publishes when sign-ups open
        if ($soiree['title'] || $past || $cancelled) {
            $soiree['themeNote'] = null;
        } elseif ($event) {
            $soiree['themeNote'] = 'Pas de thème particulier pour cette soirée.';
        } elseif ($now < $opens) {
            $soiree['themeNote'] = 'Thème annoncé à l’ouverture des inscriptions, le ' . lcfirst(self::label($opens)) . ' ' . $opens->format('Y') . '.';
        } else {
            $soiree['themeNote'] = 'Thème annoncé très bientôt, avec l’ouverture des inscriptions.';
        }
        $soiree['summary'] = self::summary($soiree);
        $soiree['googleUrl'] = $this->getGoogleUrl($soiree);
        return $soiree;
    }

    /**
     * @return \DateTimeImmutable[] start and end of the soirée of that day
     */
    private function getTimes(string $date, string $startTime) : array
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $start = new \DateTimeImmutable($date . ' ' . $startTime, $timezone);
        $end = new \DateTimeImmutable($date . ' ' . $this->end, $timezone);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        return [$start, $end];
    }

    private static function now() : \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * The admin's description, followed by its prices and page with $details; or the soirée's summary
     */
    private static function description(array $soiree, bool $details = true) : string
    {
        if ($soiree['description'] === null) {
            return $soiree['summary'] . ($details ? "\n" . $soiree['url'] : '');
        }
        $text = $soiree['description'];
        if (!$details) {
            return $text;
        }
        if ($soiree['prices']) {
            $text .= "\n" . $soiree['prices']['online'] . " € en t'inscrivant sur rockfever.be/event, "
                . $soiree['prices']['door'] . ' € sur place.';
        }
        return $text . "\n" . $soiree['url'];
    }

    /**
     * Everything about the soirée in a few sentences, for those who only read one: "Soirée Rock Fever le mercredi
     * 11 novembre 2026 au Hall Sainte-Barbe (…) : valse à 20h30, rock dès 21h, jusqu'à 1h. 3 € …"
     */
    private static function summary(array $soiree) : string
    {
        $times = array_filter([
            $soiree['waltzTime'] ? 'valse à ' . self::hour($soiree['waltzTime']) : null,
            $soiree['rockTime'] ? 'rock dès ' . self::hour($soiree['rockTime']) : null,
        ]);
        $text = ($soiree['title'] ? '« ' . $soiree['title'] . ' », soirée rock et valse du Rock Fever' : 'Soirée rock et valse du Rock Fever')
            . ($soiree['cancelled'] ? ', annulée,' : '')
            . ' le ' . lcfirst($soiree['label']) . ' ' . $soiree['year'] . ' au ' . self::location($soiree)
            . ' : ' . implode(', ', $times) . ', jusqu’à ' . self::hour($soiree['end']->format('H:i')) . ' (heure de Bruxelles).';
        if ($soiree['prices']) {
            $text .= ' ' . $soiree['prices']['online'] . ' € en s’inscrivant sur rockfever.be, '
                . $soiree['prices']['door'] . ' € sans inscription, payé à l’entrée.';
        } elseif ($soiree['priceText']) {
            $text .= ' Prix : ' . $soiree['priceText'] . '.';
        }
        $text .= ' Pour les 18-35 ans, carte d’identité ou d’étudiant obligatoire.';
        return $soiree['themeNote'] ? $text . ' ' . $soiree['themeNote'] : $text;
    }

    /**
     * "20:30" as "20h30", "21:00" as "21h", "01:00" as "1h"
     */
    private static function hour(string $time) : string
    {
        [$hours, $minutes] = explode(':', $time);
        return (int) $hours . 'h' . ($minutes === '00' ? '' : $minutes);
    }

    /**
     * The admin's text without its line-ending and indentation differences, or null when there's none
     */
    private static function paragraphs(string $text) : ?string
    {
        $lines = array_map('trim', preg_split('/\R/u', $text));
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines))) ?: null;
    }

    private static function location(array $soiree) : string
    {
        return $soiree['place'] . ($soiree['address'] ? ', ' . $soiree['address'] : '');
    }

    /**
     * "Place Sainte-Barbe 1, 1348 Louvain-la-Neuve" as a schema.org PostalAddress
     */
    private static function postalAddress(string $address) : array
    {
        if (!preg_match('/^(.+),\s*(\d{4})\s+(.+)$/u', $address, $parts)) {
            return ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => 'BE'];
        }
        return [
            '@type' => 'PostalAddress',
            'streetAddress' => $parts[1],
            'postalCode' => $parts[2],
            'addressLocality' => $parts[3],
            'addressCountry' => 'BE',
        ];
    }

    /**
     * "Hall Sainte Barbe" and "Hall Sainte-Barbe" are the same place
     */
    private static function simplify(string $place) : string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $place) ?: $place));
    }

    private function getGoogleUrl(array $soiree) : string
    {
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $soiree['name'],
            'dates' => self::utc($soiree['start']) . '/' . self::utc($soiree['end']),
            'location' => self::location($soiree),
            'details' => self::description($soiree),
            'ctz' => self::TIMEZONE,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function utc(\DateTimeImmutable $date) : string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private static function escape(string $text) : string
    {
        return str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $text);
    }

    /**
     * Lines longer than 75 bytes continue on the next line, which starts with a space (RFC 5545)
     */
    private static function fold(string $line) : string
    {
        $folded = '';
        $max = 75;
        while (strlen($line) > $max) {
            $cut = $max;
            $max = 74;
            // Don't cut a UTF-8 character in two
            while ((ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $folded .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }
        return $folded . $line;
    }
}
