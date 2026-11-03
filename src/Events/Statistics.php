<?php

namespace App\Events;

/**
 * The figures of the club's statistics page (Actions\StatisticsAction), over the past soirées of a season or of all time.
 *
 * A dancer is recognised by their first and last name (lower case, spaces trimmed): the sign-ups have no other id.
 * Statuses of a sign-up: 0 = registered (after the soirée: didn't come), 1 = came, 2 = came as VIP, 3 = came without
 * registering. The VIPs are registered dancers marked at the door, so they count as registered too (as in guests.js).
 */
class Statistics
{
    /**
     * A season runs from September to August, like the academic year
     */
    private const SEASON_START_MONTH = 9;

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * The seasons with a past soirée, latest first: [2025 => '2025-2026', ...]
     */
    public function seasons(): array
    {
        $seasons = [];
        $rows = $this->pdo->query(
            'SELECT DISTINCT YEAR(date) - (MONTH(date) < ' . self::SEASON_START_MONTH . ') AS season
             FROM events WHERE date < CURDATE() ORDER BY season DESC'
        )->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($rows as $season) {
            $seasons[(int)$season] = $season . '-' . ($season + 1);
        }
        return $seasons;
    }

    /**
     * @param int|null $season The year the season starts in, null for all time
     */
    public function compute(?int $season): array
    {
        $from = $season === null ? null : sprintf('%d-%02d-01', $season, self::SEASON_START_MONTH);
        $to = $season === null ? null : sprintf('%d-%02d-01', $season + 1, self::SEASON_START_MONTH);

        // Every sign-up of every past soirée: newcomers and returns need the dancers' whole history
        $rows = $this->pdo->query(
            "SELECT e.id AS event_id, e.title, e.date AS event_date, d.firstname, d.lastname, d.status,
                    DATE_FORMAT(d.date, '%H:%i') AS arrival
             FROM events e JOIN dancers d ON d.event_id = e.id
             WHERE e.date < CURDATE()
             ORDER BY e.date ASC, e.id ASC, d.id ASC"
        )->fetchAll(\PDO::FETCH_OBJ);

        foreach ($rows as $row) {
            $row->firstname = $this->repair(trim($row->firstname));
            $row->lastname = $this->repair(trim($row->lastname));
            $row->title = $this->repair($row->title);
        }
        $keys = $this->personKeys($rows);

        $events = [];
        $people = [];
        foreach ($rows as $i => $row) {
            $inPeriod = $from === null || ($row->event_date >= $from && $row->event_date < $to);
            $key = $keys[$i];
            $came = (int)$row->status > 0;
            $name = $row->firstname . ' ' . $row->lastname;

            $people[$key] ??= [
                'name' => $name,
                'visits' => [],       // the soirées they came to, in order
                'periodVisits' => 0,
                'periodNoShows' => 0,
                'periodRegistered' => 0,
            ];
            // Shown with a spelling that lost no letter, when one of their sign-ups has it
            if ($this->hasLostLetters($people[$key]['name']) && !$this->hasLostLetters($name)) {
                $people[$key]['name'] = $name;
            }
            if ($came) {
                $people[$key]['visits'][] = (int)$row->event_id;
            }
            if (!$inPeriod) {
                continue;
            }

            $events[$row->event_id] ??= [
                'id' => (int)$row->event_id,
                'title' => $row->title,
                'date' => $row->event_date,
                'registered' => 0,
                'came' => 0,
                'noShows' => 0,
                'vip' => 0,
                'walkIns' => 0,
                'newcomers' => 0,
                'arrivals' => [],
            ];
            $event = &$events[$row->event_id];
            switch ((int)$row->status) {
                case 0:
                    $event['registered']++;
                    $event['noShows']++;
                    $people[$key]['periodNoShows']++;
                    $people[$key]['periodRegistered']++;
                    break;
                case 1:
                    $event['registered']++;
                    $people[$key]['periodRegistered']++;
                    break;
                case 2:
                    $event['registered']++;
                    $event['vip']++;
                    $people[$key]['periodRegistered']++;
                    break;
                case 3:
                    $event['walkIns']++;
                    break;
            }
            if ($came) {
                $event['came']++;
                $people[$key]['periodVisits']++;
                // A first visit: the soirée they came to is the first of their history
                if (count($people[$key]['visits']) === 1) {
                    $event['newcomers']++;
                }
                if ($row->arrival !== null) {
                    $event['arrivals'][] = $this->arrivalMinutes($row->arrival);
                }
            }
            unset($event);
        }

        return [
            'totals' => $this->totals($events, $people),
            'events' => array_values($events),
            'loyalty' => $this->loyalty($people),
            'regulars' => $this->regulars($people),
            'noShowers' => $this->noShowers($people),
            'arrivals' => $this->arrivals($events),
        ];
    }

    private function totals(array $events, array $people): array
    {
        $sum = fn (string $field) => array_sum(array_column($events, $field));
        $attendances = $sum('came');
        $registered = $sum('registered');
        $newcomers = $sum('newcomers');

        $dancers = 0;
        $repeaters = 0;
        // The newcomers of the period who came again later, of those who had a later soirée to come back to
        $newcomersWithLaterSoiree = 0;
        $newcomersBack = 0;
        $lastEventId = $this->lastPastEventId();
        $periodEventIds = array_flip(array_column($events, 'id'));
        foreach ($people as $person) {
            if ($person['periodVisits'] === 0) {
                continue;
            }
            $dancers++;
            if ($person['periodVisits'] >= 2) {
                $repeaters++;
            }
            $first = $person['visits'][0];
            if (isset($periodEventIds[$first]) && $first !== $lastEventId) {
                $newcomersWithLaterSoiree++;
                if (count($person['visits']) >= 2) {
                    $newcomersBack++;
                }
            }
        }

        $record = null;
        foreach ($events as $event) {
            if ($record === null || $event['came'] > $record['came']) {
                $record = $event;
            }
        }

        return [
            'events' => count($events),
            'attendances' => $attendances,
            'average' => count($events) ? $attendances / count($events) : 0,
            'registered' => $registered,
            'noShows' => $sum('noShows'),
            'noShowRate' => $this->rate($sum('noShows'), $registered),
            'walkIns' => $sum('walkIns'),
            'walkInRate' => $this->rate($sum('walkIns'), $attendances),
            'vip' => $sum('vip'),
            'dancers' => $dancers,
            'repeaters' => $repeaters,
            'repeaterRate' => $this->rate($repeaters, $dancers),
            'newcomers' => $newcomers,
            'newcomerRate' => $this->rate($newcomers, $dancers),
            'returningRate' => $this->rate($attendances - $newcomers, $attendances),
            'visitsPerDancer' => $dancers ? $attendances / $dancers : 0,
            'newcomersBack' => $newcomersBack,
            'newcomersBackRate' => $this->rate($newcomersBack, $newcomersWithLaterSoiree),
            'record' => $record,
        ];
    }

    /**
     * How many dancers came once, twice, ... during the period
     */
    private function loyalty(array $people): array
    {
        $buckets = ['1 fois' => 0, '2 fois' => 0, '3-4 fois' => 0, '5-9 fois' => 0, '10 fois ou plus' => 0];
        foreach ($people as $person) {
            $visits = $person['periodVisits'];
            if ($visits === 0) {
                continue;
            }
            $bucket = match (true) {
                $visits === 1 => '1 fois',
                $visits === 2 => '2 fois',
                $visits <= 4 => '3-4 fois',
                $visits <= 9 => '5-9 fois',
                default => '10 fois ou plus',
            };
            $buckets[$bucket]++;
        }
        return $buckets;
    }

    /**
     * The 10 dancers who came the most during the period
     */
    private function regulars(array $people): array
    {
        $regulars = array_filter($people, fn ($person) => $person['periodVisits'] >= 2);
        uasort($regulars, fn ($a, $b) => [$b['periodVisits'], $a['name']] <=> [$a['periodVisits'], $b['name']]);
        return array_slice(array_values($regulars), 0, 10);
    }

    /**
     * The 10 dancers who registered and didn't come the most often during the period (twice at least)
     */
    private function noShowers(array $people): array
    {
        $noShowers = array_filter($people, fn ($person) => $person['periodNoShows'] >= 2);
        foreach ($noShowers as &$person) {
            $person['noShowRate'] = $this->rate($person['periodNoShows'], $person['periodRegistered']);
        }
        unset($person);
        uasort($noShowers, fn ($a, $b) =>
            [$b['periodNoShows'], $b['noShowRate'], $a['name']] <=> [$a['periodNoShows'], $a['noShowRate'], $b['name']]);
        return array_slice(array_values($noShowers), 0, 10);
    }

    /**
     * The arrivals at the door by quarter of an hour ['20:00' => 12, ...], and the median arrival time
     */
    private function arrivals(array $events): array
    {
        $minutes = array_merge([], ...array_column($events, 'arrivals'));
        if (!$minutes) {
            return ['slots' => [], 'median' => null, 'peak' => null];
        }
        sort($minutes);

        $slots = [];
        $first = intdiv($minutes[0], 15) * 15;
        $last = intdiv($minutes[count($minutes) - 1], 15) * 15;
        for ($slot = $first; $slot <= $last; $slot += 15) {
            $slots[$this->clock($slot)] = 0;
        }
        foreach ($minutes as $minute) {
            $slots[$this->clock(intdiv($minute, 15) * 15)]++;
        }

        return [
            'slots' => $slots,
            'median' => $this->clock($minutes[intdiv(count($minutes), 2)]),
            'peak' => array_search(max($slots), $slots, true),
        ];
    }

    private function lastPastEventId(): ?int
    {
        $id = $this->pdo->query('SELECT id FROM events WHERE date < CURDATE() ORDER BY date DESC, id DESC LIMIT 1')
            ->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    /**
     * The dancer of each sign-up. A name whose letters were lost to "?" goes to the one dancer it can be the
     * spelling of ("Zo? Dupont" to "Zoé Dupont"), and stays apart when it could be several of them.
     */
    private function personKeys(array $rows): array
    {
        $keys = array_map(fn ($row) => $this->personKey($row->firstname, $row->lastname), $rows);

        $complete = array_filter(array_unique($keys), fn ($key) => !$this->hasLostLetters($key));
        $matches = [];
        foreach (array_unique($keys) as $key) {
            if (!$this->hasLostLetters($key)) {
                continue;
            }
            // A lost letter left 1 or 2 "?" (one a byte): n of them stand for n/2 to n letters
            $pattern = '/^' . preg_replace_callback(
                '/(?:\\\\\?|\x{FFFD})+/u',
                function ($run) {
                    $lost = mb_strlen(str_replace('\\', '', $run[0]));
                    return '.{' . (int)ceil($lost / 2) . ',' . $lost . '}';
                },
                preg_quote($key, '/')
            ) . '$/u';
            $candidates = preg_grep($pattern, $complete);
            if (count($candidates) === 1) {
                $matches[$key] = reset($candidates);
            }
        }

        return array_map(fn ($key) => $matches[$key] ?? $key, $keys);
    }

    /**
     * A name as it compares to its other spellings: lower case, no accents, hyphens and apostrophes as spaces
     */
    private function personKey(string $firstname, string $lastname): string
    {
        $key = mb_strtolower($firstname . '|' . $lastname);
        if (class_exists(\Normalizer::class)) {
            $key = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($key, \Normalizer::FORM_D));
        }
        $key = preg_replace(['/[-\'’.]+/u', '/\s+/u'], ' ', $key);
        return preg_replace('/ ?\| ?/', '|', trim($key));
    }

    private function hasLostLetters(string $name): bool
    {
        return str_contains($name, '?') || str_contains($name, "\u{FFFD}");
    }

    /**
     * Text saved double-encoded ("ZoÃ©" for "Zoé") back to what was typed, as bin/fix-double-utf8.php does in the
     * database: only when every character is one of latin1's and their bytes make valid UTF-8. Twice for the
     * names encoded twice over.
     */
    private function repair(string $text): string
    {
        static $latin1 = null;
        if ($latin1 === null) {
            // MySQL's latin1 is Windows-1252, with its 5 unassigned bytes mapped to the C1 controls of the same number
            $latin1 = [];
            for ($byte = 0x80; $byte <= 0xFF; $byte++) {
                $char = mb_convert_encoding(chr($byte), 'UTF-8', 'Windows-1252');
                $latin1[$char === '?' ? mb_chr($byte) : $char] = chr($byte);
            }
        }

        for ($pass = 0; $pass < 2 && preg_match('/[^\x00-\x7F]/', $text); $pass++) {
            $bytes = '';
            foreach (mb_str_split($text) as $char) {
                if (strlen($char) === 1) {
                    $bytes .= $char;
                } elseif (isset($latin1[$char])) {
                    $bytes .= $latin1[$char];
                } else {
                    return $text;
                }
            }
            if (!mb_check_encoding($bytes, 'UTF-8')) {
                return $text;
            }
            $text = $bytes;
        }
        return $text;
    }

    /**
     * Minutes since the start of the soirée's day: an arrival after midnight still comes after one at 23:00
     */
    private function arrivalMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        if ($hours < 12) {
            $hours += 24;
        }
        return $hours * 60 + $minutes;
    }

    private function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    /**
     * A percentage, null when there's nothing to divide
     */
    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? 100 * $part / $whole : null;
    }
}
