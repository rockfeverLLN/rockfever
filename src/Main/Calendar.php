<?php
namespace App\Main;

use App\Events\Entity\Event;
use App\Events\Table\EventTable;

/**
 * The soirées, each one put together in one place: its date in src/Main/config.php and its event in the admin,
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
     * @var array[] the dates of src/Main/config.php, oldest first
     */
    private $dates;

    /**
     * @var array[]|null every soirée at the time of the request, once built
     */
    private $all;

    public function __construct(
        array $soirees,
        private string $start,
        private string $rock,
        private string $end,
        private string $place,
        private string $address,
        private array $prices,
        private EventTable $events
    ) {
        usort($soirees, fn ($a, $b) => strcmp($a['date'], $b['date']));
        $this->dates = $soirees;
    }

    /**
     * Every soirée, oldest first: the dates of the config and the events of the admin (back to the first one)
     * @return array[]
     */
    public function all(?\DateTimeImmutable $now = null) : array
    {
        if ($now === null && $this->all !== null) {
            return $this->all;
        }
        $listed = array_column($this->dates, null, 'date');
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
     * The soirées of /calendrier and the home page: the dates of the config, and the admin's events from the first of
     * them on (or still to come)
     * @return array[]
     */
    public function getSoirees(?\DateTimeImmutable $now = null) : array
    {
        $first = $this->dates[0]['date'] ?? null;
        return array_values(array_filter(
            $this->all($now),
            fn (array $soiree) => $soiree['listed'] || !$soiree['past'] || ($first !== null && $soiree['date'] >= $first)
        ));
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
        $last = end($this->dates);
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
            'organizer' => ['@type' => 'Organization', '@id' => self::SITE . '/#organization', 'name' => 'Rock Fever', 'url' => self::SITE . '/'],
        ];
        if ($soiree['prices'] && !$soiree['past']) {
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
                    'availability' => 'https://schema.org/' . ($soiree['cancelled'] ? 'OutOfStock' : 'InStock'),
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
                'facebook' => $soiree['facebookLink'] ?: null,
            ]),
        ];
    }

    /**
     * One soirée, from its date in the config ($listed) and its event in the admin, either of which can be missing
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

        // Sign-ups open Event::REGISTRATION_DAYS before, but not before the previous soirée is over: only the next
        // soirée can have them open. They close when it starts
        $opens = Event::registrationOpeningFor($day);
        if ($previousEnd !== null && $previousEnd > $opens) {
            $opens = $previousEnd;
        }
        if ($cancelled) {
            $status = self::REGISTRATION_CANCELLED;
        } elseif (($event && !empty($event->closed)) || $now >= $start) {
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
            // The prices of the config are those of the listed soirées and those to come; older events only have the
            // admin's text
            'prices' => $listed !== null || !$past ? $this->prices : null,
            'priceText' => $event ? (trim((string) $event->price) ?: null) : null,
            'facebookLink' => $event?->facebookLink ?: null,
            'coveventLink' => $event?->coveventLink ?? null,
            'cancelled' => $cancelled,
            'past' => $past,
            'registration' => ['status' => $status, 'opens' => $opens, 'closes' => $start],
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
