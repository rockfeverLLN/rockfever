<?php

namespace App\Events;

/**
 * Who is who among the sign-ups, which have no id but the name the dancer typed.
 *
 * Every spelling has a key: its first and last name in lower case, without accents, hyphens or apostrophes. Two sign-ups
 * with the same key are the same dancer. The admin's page /admin/statistics/names settles the other cases, kept in
 * dancer_matches: a key that is another spelling of a dancer (same = 1), or two keys that are different people (same = 0).
 * The sign-ups keep what was typed: only the statistics read these matches, and each one can be undone.
 */
class DancerNames
{
    /**
     * The suggestions shown at once: enough for one sitting, and the next ones come once these are settled
     */
    private const SUGGESTIONS_SHOWN = 40;

    /**
     * The ranks of the pairs that mergeSure() settles, and of the swapped names it leaves to the team
     */
    private const SURE = 0;
    private const SWAPPED = 1;

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Cleans the names of sign-ups (rows with a firstname and a lastname) and gives each one's dancer. A name whose
     * letters were lost to "?" goes to the one dancer it can be the spelling of ("Zo? Dupont" to "Zoé Dupont"), and stays
     * apart when it could be several of them. Each row's own key is left in its nameKey.
     *
     * @return string[] The dancer of each row, by the row's index
     */
    public function identify(array $rows): array
    {
        $aliases = $this->aliases();
        $keys = [];
        foreach ($rows as $i => $row) {
            $row->firstname = $this->repair(trim($row->firstname));
            $row->lastname = $this->repair(trim($row->lastname));
            $row->nameKey = $this->personKey($row->firstname, $row->lastname);
            $keys[$i] = $row->nameKey;
        }

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

        return array_map(fn ($key) => $this->resolve($matches[$key] ?? $key, $aliases), $keys);
    }

    /**
     * The name to show for each dancer: the spelling used most among those of their own key with all their letters
     *
     * @param array $rows Rows that went through identify()
     * @param string[] $keys The key of each row, by the row's index
     * @return string[] [key => name]
     */
    public function labels(array $rows, array $keys): array
    {
        $spellings = [];
        foreach ($rows as $i => $row) {
            $name = $row->firstname . ' ' . $row->lastname;
            $complete = !$this->hasLostLetters($name);
            $spellings[$keys[$i]][$name] ??= [$complete && $row->nameKey === $keys[$i], $complete, 0];
            $spellings[$keys[$i]][$name][2]++;
        }
        return array_map(function ($names) {
            arsort($names);
            return (string)array_key_first($names);
        }, $spellings);
    }

    /**
     * Every dancer of the past soirées, as the statistics count them
     *
     * @return array [key => ['key', 'name', 'signUps']], by name
     */
    public function dancers(): array
    {
        $rows = $this->signUps();
        $keys = $this->identify($rows);
        $labels = $this->labels($rows, $keys);

        $dancers = [];
        foreach ($keys as $key) {
            $dancers[$key] ??= ['key' => $key, 'name' => $labels[$key], 'signUps' => 0];
            $dancers[$key]['signUps']++;
        }
        uasort($dancers, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $dancers;
    }

    /**
     * The pairs of dancers that look like one person and that nobody settled yet, the surest first
     *
     * @param array $dancers The result of dancers()
     * @return array ['total' => how many there are, 'sure' => how many mergeSure() would settle,
     *               'pairs' => the first of them: ['a', 'b' (dancers), 'reason']]
     */
    public function suggestions(array $dancers): array
    {
        $pairs = $this->pairs($dancers);
        return [
            'total' => count($pairs),
            'sure' => count(array_filter($pairs, fn ($pair) => $pair['rank'] === self::SURE)),
            'pairs' => array_slice($pairs, 0, self::SUGGESTIONS_SHOWN),
        ];
    }

    /**
     * Answers "same person" to the pairs that leave little doubt: the same letters, whose spellings only differ by their
     * spaces or by where the first name ends, and the swapped names that rightWayRound() can tell apart. The first ones
     * keep the name used most, the others the first name in front.
     *
     * @param array $dancers The result of dancers()
     * @return int How many pairs that settled
     */
    public function mergeSure(array $dancers): int
    {
        $sure = array_filter($this->pairs($dancers), fn ($pair) => $pair['rank'] === self::SURE);
        foreach ($sure as $pair) {
            $this->merge($pair['b']['key'], $pair['a']['key']);
        }
        return count($sure);
    }

    /**
     * @param array $dancers The result of dancers()
     * @return array Every pair still to settle, the surest first: ['a', 'b' (dancers), 'rank', 'reason']
     */
    private function pairs(array $dancers): array
    {
        $different = [];
        foreach ($this->matches() as $match) {
            if (!$match->same) {
                $different[$this->pairId($match->name_key, $match->match_key)] = true;
            }
        }

        $lookalikes = array_diff_key($this->lookalikes(array_keys($dancers)), $different);
        $firstNames = $this->firstNames(array_keys($dancers), $lookalikes);

        $pairs = [];
        foreach ($lookalikes as [$a, $b, [$rank, $reason]]) {
            // The spelling used most on the left: it's most often the right one
            if ($dancers[$a]['signUps'] < $dancers[$b]['signUps']) {
                [$a, $b] = [$b, $a];
            }
            $rightWay = $rank === self::SWAPPED ? $this->rightWayRound($a, $b, $firstNames) : null;
            if ($rightWay !== null) {
                [$a, $b] = $rightWay;
                $rank = self::SURE;
            }
            $pairs[] = ['a' => $dancers[$a], 'b' => $dancers[$b], 'rank' => $rank, 'reason' => $reason];
        }
        // A slip is rare next to the right spelling: two names used as often are more likely two people
        usort($pairs, fn ($x, $y) => [
            $x['rank'],
            $x['b']['signUps'] / $x['a']['signUps'],
            $y['a']['signUps'] + $y['b']['signUps'],
            $x['a']['name'],
        ] <=> [
            $y['rank'],
            $y['b']['signUps'] / $y['a']['signUps'],
            $x['a']['signUps'] + $x['b']['signUps'],
            $y['a']['name'],
        ]);
        return $pairs;
    }

    /**
     * The first names the dancers are known by, leaving out the swapped spellings, which can't be trusted on it
     *
     * @param string[] $keys
     * @param array $lookalikes The result of lookalikes()
     * @return array [first name without spaces => true]
     */
    private function firstNames(array $keys, array $lookalikes): array
    {
        $swapped = [];
        foreach ($lookalikes as [$a, $b, [$rank]]) {
            if ($rank === self::SWAPPED) {
                $swapped[$a] = $swapped[$b] = true;
            }
        }

        $firstNames = [];
        foreach (array_diff($keys, array_keys($swapped)) as $key) {
            $firstNames[$this->squeezedFirstName($key)] = true;
        }
        return $firstNames;
    }

    /**
     * Which of two swapped spellings has the first name in front, when other dancers tell: "Madeleine" is the first name
     * of others and "Barbez" of nobody. When both are first names, "Thomas Martin" and "Martin Thomas" can be two people;
     * when neither is, nothing says which way round it goes.
     *
     * @param array $firstNames The result of firstNames()
     * @return string[]|null The right spelling then the swapped one, null when only the team can tell
     */
    private function rightWayRound(string $a, string $b, array $firstNames): ?array
    {
        $firstInA = isset($firstNames[$this->squeezedFirstName($a)]);
        $firstInB = isset($firstNames[$this->squeezedFirstName($b)]);
        if ($firstInA === $firstInB) {
            return null;
        }
        return $firstInA ? [$a, $b] : [$b, $a];
    }

    private function squeezedFirstName(string $key): string
    {
        return str_replace(' ', '', explode('|', $key, 2)[0]);
    }

    /**
     * The choices made so far, with the names to show them by: latest first
     *
     * @return array ['same' => [['id', 'name', 'other']], 'different' => [...]]
     */
    public function choices(): array
    {
        $rows = $this->signUps();
        $this->identify($rows);
        $labels = $this->labels($rows, array_column($rows, 'nameKey'));

        $choices = ['same' => [], 'different' => []];
        foreach (array_reverse($this->matches()) as $match) {
            $choices[$match->same ? 'same' : 'different'][] = [
                'id' => (int)$match->id,
                'name' => $labels[$match->name_key] ?? str_replace('|', ' ', $match->name_key),
                'other' => $labels[$match->match_key] ?? str_replace('|', ' ', $match->match_key),
            ];
        }
        return $choices;
    }

    /**
     * The dancer a typed name stands for, whatever its case and accents
     *
     * @param array $dancers The result of dancers()
     */
    public function find(string $name, array $dancers): ?string
    {
        $name = str_replace('|', '', $this->personKey($name, ''));
        foreach ($dancers as $key => $dancer) {
            if ($name !== '' && $name === str_replace('|', ' ', trim($key, '|'))) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Counts $spelling and all its other spellings as the dancer $dancer from now on
     */
    public function merge(string $spelling, string $dancer): void
    {
        $aliases = $this->aliases();
        $spelling = $this->resolve($spelling, $aliases);
        $dancer = $this->resolve($dancer, $aliases);
        if ($spelling === $dancer) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM dancer_matches WHERE same = 0 AND name_key = ? AND match_key = ?')
                ->execute($this->sorted($spelling, $dancer));
            // Straight to the dancer, not through the old one: a spelling never leads to another one
            $this->pdo->prepare('UPDATE dancer_matches SET match_key = ? WHERE same = 1 AND match_key = ?')
                ->execute([$dancer, $spelling]);
            $this->insert($spelling, $dancer, true);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Stops suggesting that these two are the same person
     */
    public function separate(string $a, string $b): void
    {
        [$a, $b] = $this->sorted($a, $b);
        $found = $this->pdo->prepare('SELECT COUNT(*) FROM dancer_matches WHERE same = 0 AND name_key = ? AND match_key = ?');
        $found->execute([$a, $b]);
        if ($a !== $b && !$found->fetchColumn()) {
            $this->insert($a, $b, false);
        }
    }

    /**
     * Takes back one of the choices()
     */
    public function undo(int $id): void
    {
        $this->pdo->prepare('DELETE FROM dancer_matches WHERE id = ?')->execute([$id]);
    }

    /**
     * Text saved double-encoded ("ZoÃ©" for "Zoé") back to what was typed, as bin/fix-double-utf8.php does in the
     * database: only when every character is one of latin1's and their bytes make valid UTF-8. Twice for the
     * names encoded twice over.
     */
    public function repair(string $text): string
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
     * The sign-ups of the past soirées and tonight's, the ones the statistics count
     */
    private function signUps(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.firstname, d.lastname FROM dancers d JOIN events e ON e.id = d.event_id
             WHERE e.date <= ? ORDER BY e.date ASC, e.id ASC, d.id ASC'
        );
        $statement->execute([(new \DateTimeImmutable('now', new \DateTimeZone(\App\Main\Calendar::TIMEZONE)))->format('Y-m-d')]);
        return $statement->fetchAll(\PDO::FETCH_OBJ);
    }

    private function matches(): array
    {
        return $this->pdo->query('SELECT id, name_key, match_key, same FROM dancer_matches ORDER BY id ASC')
            ->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * @return string[] [spelling => the dancer it's a spelling of]
     */
    private function aliases(): array
    {
        $aliases = [];
        foreach ($this->matches() as $match) {
            if ($match->same) {
                $aliases[$match->name_key] = $match->match_key;
            }
        }
        return $aliases;
    }

    private function resolve(string $key, array $aliases): string
    {
        $seen = [];
        while (isset($aliases[$key]) && !isset($seen[$key])) {
            $seen[$key] = true;
            $key = $aliases[$key];
        }
        return $key;
    }

    private function insert(string $key, string $match, bool $same): void
    {
        $this->pdo->prepare('INSERT INTO dancer_matches (name_key, match_key, same) VALUES (?, ?, ?)')
            ->execute([$key, $match, (int)$same]);
    }

    /**
     * The pairs of keys that look like one dancer. Only those that share their first name, their last name, their letters
     * or their initials are compared, as we go: everyone with everyone is millions of pairs with a few thousand dancers
     *
     * @param string[] $keys
     * @return array [pair id => [key, key, likeness()]]
     */
    private function lookalikes(array $keys): array
    {
        $groups = [];
        $names = [];
        foreach ($keys as $key) {
            [$first, $last] = explode('|', $key, 2) + [1 => ''];
            $squeezed = [str_replace(' ', '', $first), str_replace(' ', '', $last)];
            $names[$key] = [$first, $last, $squeezed[0], $squeezed[1]];
            if ($first !== '') {
                $groups["first $first"][] = $key;
            }
            if ($last !== '') {
                $groups["last $last"][] = $key;
            }
            $groups['initials ' . mb_substr($first, 0, 1) . mb_substr($last, 0, 1)][] = $key;
            $groups['letters ' . implode('', $squeezed)][] = $key;
            sort($squeezed);
            $groups['swapped ' . implode('', $squeezed)][] = $key;
        }

        $found = [];
        foreach ($groups as $group) {
            $size = count($group);
            for ($i = 0; $i < $size; $i++) {
                for ($j = $i + 1; $j < $size; $j++) {
                    $likeness = $this->likeness($names[$group[$i]], $names[$group[$j]]);
                    if ($likeness !== null) {
                        $found[$this->pairId($group[$i], $group[$j])] = [$group[$i], $group[$j], $likeness];
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Why two spellings look like one dancer
     *
     * @param string[] $a The first name, last name, and both without spaces
     * @param string[] $b The same for the other spelling
     * @return array|null [rank, the reason shown], the surest with the lowest rank. Null when they don't
     */
    private function likeness(array $a, array $b): ?array
    {
        [$firstA, $lastA, $squeezedFirstA, $squeezedLastA] = $a;
        [$firstB, $lastB, $squeezedFirstB, $squeezedLastB] = $b;
        $sameFirst = $squeezedFirstA === $squeezedFirstB;
        $sameLast = $squeezedLastA === $squeezedLastB;

        if ($squeezedFirstA . $squeezedLastA === $squeezedFirstB . $squeezedLastB) {
            return [self::SURE, 'Mêmes lettres'];
        }
        if ($squeezedFirstA === $squeezedLastB && $squeezedLastA === $squeezedFirstB) {
            return [self::SWAPPED, 'Prénom et nom inversés'];
        }
        if ($firstA === '' || $lastA === '' || $firstB === '' || $lastB === '') {
            return null;
        }
        // Most pairs stop here: with neither name in common, two typos can't make up for more than two letters
        if (!$sameFirst && !$sameLast
            && abs(strlen($firstA) + strlen($lastA) - strlen($firstB) - strlen($lastB)) > 2
        ) {
            return null;
        }

        $firstTypos = levenshtein($firstA, $firstB);
        $lastTypos = levenshtein($lastA, $lastB);
        if ($firstTypos + $lastTypos <= 2 && $this->slip($firstA, $firstB, $firstTypos)
            && $this->slip($lastA, $lastB, $lastTypos)
        ) {
            return [2, 'Faute de frappe'];
        }

        // "Alex" for "Alexandre", "Biolley" for "de Biolley"
        if (($sameLast && $this->shortened($firstA, $firstB))
            || ($sameFirst && (array_diff(explode(' ', $lastA), explode(' ', $lastB)) === []
                    || array_diff(explode(' ', $lastB), explode(' ', $lastA)) === []))
        ) {
            return [3, 'Nom raccourci'];
        }
        return null;
    }

    /**
     * Whether a name's typos are few enough for its length: "Dupond" can be "Dupont", "Anna" is not "Emma"
     */
    private function slip(string $a, string $b, int $typos): bool
    {
        return $typos * 3 <= max(strlen($a), strlen($b));
    }

    private function shortened(string $a, string $b): bool
    {
        [$short, $long] = strlen($a) < strlen($b) ? [$a, $b] : [$b, $a];
        return strlen($short) >= 3 && str_starts_with($long, $short);
    }

    private function pairId(string $a, string $b): string
    {
        return implode("\0", $this->sorted($a, $b));
    }

    /**
     * @return string[] The two keys, always in the same order
     */
    private function sorted(string $a, string $b): array
    {
        return strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
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
}
