<?php
namespace App\Main;

/**
 * The soirées listed in src/Main/config.php, with what the calendar page and its .ics files need
 */
class Calendar
{
    const TIMEZONE = 'Europe/Brussels';

    const TITLE = 'Soirée Rock Fever';

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
     * @var array[]
     */
    private $soirees;

    public function __construct(
        array $soirees,
        private string $start,
        private string $end,
        private string $place,
        private string $address
    ) {
        usort($soirees, fn ($a, $b) => strcmp($a['date'], $b['date']));
        $this->soirees = $soirees;
    }

    /**
     * Every soirée, oldest first, as arrays ready for the template
     * @return array[]
     */
    public function getSoirees(?\DateTimeImmutable $now = null) : array
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $nextFound = false;
        return array_map(function (array $soiree) use ($now, &$nextFound) {
            [$start, $end] = $this->getTimes($soiree['date']);
            $past = $end < $now;
            $next = !$past && !$nextFound;
            $nextFound = $nextFound || $next;
            return [
                'date' => $soiree['date'],
                'week' => $soiree['week'] ?? null,
                'start' => $start,
                'end' => $end,
                'day' => $start->format('j'),
                'weekday' => self::WEEKDAYS[$start->format('w')],
                'month' => self::MONTHS[$start->format('n') - 1],
                'shortMonth' => self::SHORT_MONTHS[$start->format('n') - 1],
                'year' => $start->format('Y'),
                'past' => $past,
                'next' => $next,
                'googleUrl' => $this->getGoogleUrl($start, $end),
            ];
        }, $this->soirees);
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
        $last = end($this->soirees);
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

    public function has(string $date) : bool
    {
        return in_array($date, array_column($this->soirees, 'date'), true);
    }

    /**
     * iCalendar file with the given soirées (all of them when $date is null)
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
        foreach ($this->soirees as $soiree) {
            if ($date !== null && $soiree['date'] !== $date) {
                continue;
            }
            [$start, $end] = $this->getTimes($soiree['date']);
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:soiree-' . $soiree['date'] . '@rockfever.be',
                'DTSTAMP:' . $stamp,
                'DTSTART:' . self::utc($start),
                'DTEND:' . self::utc($end),
                'SUMMARY:' . self::escape(self::TITLE),
                'LOCATION:' . self::escape($this->place . ', ' . $this->address),
                'DESCRIPTION:' . self::escape($this->getDescription()),
                'URL:https://rockfever.be/calendrier',
                'END:VEVENT',
            ]);
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /**
     * @return \DateTimeImmutable[] start and end of the soirée of that day
     */
    private function getTimes(string $date) : array
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $start = new \DateTimeImmutable($date . ' ' . $this->start, $timezone);
        $end = new \DateTimeImmutable($date . ' ' . $this->end, $timezone);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        return [$start, $end];
    }

    private function getDescription() : string
    {
        return "Soirée rock et valse du Rock Fever, ouverte aux 18-35 ans (carte d'identité obligatoire).\n"
            . "3 € en t'inscrivant sur rockfever.be/event, 4 € sur place.\n"
            . 'https://rockfever.be/calendrier';
    }

    private function getGoogleUrl(\DateTimeImmutable $start, \DateTimeImmutable $end) : string
    {
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => self::TITLE,
            'dates' => self::utc($start) . '/' . self::utc($end),
            'location' => $this->place . ', ' . $this->address,
            'details' => $this->getDescription(),
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
