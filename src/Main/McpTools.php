<?php
namespace App\Main;

use App\Events\Table\DancerTable;
use Framework\Database\NoRecordException;
use Framework\SpamGuard;
use Psr\Http\Message\ServerRequestInterface;

/**
 * What an AI assistant can do through /mcp (App\Main\Actions\McpAction): read the soirées, and sign someone up in two
 * steps, the way the form of /event does.
 *
 * Visitors have no accounts, so nothing here identifies the person. A sign-up is an ordinary line of the door list, like
 * one from the form: nothing else is kept, so assistants can't cancel it, the team does. And as on the form, the answer is
 * the same whether or not the name was already on the list, or anyone could find out who is coming.
 */
class McpTools
{
    // Per IP address. Higher than the form's: the people using one assistant all arrive from its few addresses
    const MAX_SIGN_UPS = 60;
    const MAX_SIGN_UPS_DELAY = 3600;

    // How long the person has to say yes to a preview
    const TOKEN_LIFETIME = 900;

    const TOKEN_PREFIX = 'rf1.';

    const INSTRUCTIONS = 'Soirées rock et valse du Rock Fever (18-35 ans, Louvain-la-Neuve). Pour inscrire quelqu\'un : '
        . 'prepare_registration, montre le résumé à la personne, puis confirm_registration seulement après son accord '
        . 'explicite. N\'inscris que la personne qui le demande, avec son propre nom : l\'inscription met un vrai nom sur '
        . 'la liste d\'entrée et vaut acceptation du R.O.I. On paie à l\'entrée. Pour annuler, la personne écrit à '
        . 'l\'équipe sur ' . Calendar::SITE . '/contact.';

    public function __construct(
        private Calendar $calendar,
        private DancerTable $dancers,
        private SpamGuard $spamGuard
    ) {
    }

    /**
     * The tools, as tools/list gives them. Those without readOnlyHint are the ones assistants ask the person to approve
     */
    public function definitions() : array
    {
        $eventId = [
            'type' => 'string',
            'pattern' => '^\d{4}-\d{2}-\d{2}$',
            'description' => 'Le jour de la soirée (AAAA-MM-JJ), son id dans list_events.',
        ];
        $read = ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false];
        return [
            [
                'name' => 'list_events',
                'title' => 'Lister les soirées à venir',
                'description' => 'Les soirées à venir, avec leurs heures, leur lieu, leurs prix et l\'état de leurs inscriptions.',
                // An empty `properties`, which OpenAI's tools need even without any parameter
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false],
                'annotations' => $read,
            ],
            [
                'name' => 'get_event',
                'title' => 'Détails d\'une soirée',
                'description' => 'Une soirée, passée ou à venir, et l\'état de ses inscriptions.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['event_id' => $eventId],
                    'required' => ['event_id'],
                    'additionalProperties' => false,
                ],
                'annotations' => $read,
            ],
            [
                'name' => 'prepare_registration',
                'title' => 'Préparer une inscription',
                'description' => 'Vérifie qu\'une inscription est possible et en donne le résumé, sans rien enregistrer. '
                    . 'Montre ce résumé à la personne, avec le lien du R.O.I., avant confirm_registration.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => ['description' => 'Par défaut, la prochaine soirée.'] + $eventId,
                        'firstname' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Prénom de la personne.'],
                        'lastname' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Nom de la personne.'],
                    ],
                    'required' => ['firstname', 'lastname'],
                    'additionalProperties' => false,
                ],
                'annotations' => $read,
            ],
            [
                'name' => 'confirm_registration',
                'title' => 'Confirmer une inscription',
                'description' => 'Met la personne sur la liste d\'entrée. À n\'appeler qu\'après son accord explicite sur '
                    . 'le résumé de prepare_registration. Renvoyer le même jeton n\'inscrit pas deux fois.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'confirmation_token' => [
                            'type' => 'string',
                            'description' => 'Le confirmation_token donné par prepare_registration.',
                        ],
                    ],
                    'required' => ['confirmation_token'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            ],
        ];
    }

    public function has(string $name) : bool
    {
        return in_array($name, array_column($this->definitions(), 'name'), true);
    }

    /**
     * @throws \DomainException with a message for the person: the event doesn't exist, sign-ups are closed...
     */
    public function call(string $name, array $arguments, ServerRequestInterface $request) : array
    {
        return match ($name) {
            'list_events' => $this->listEvents(),
            'get_event' => $this->getEvent($arguments),
            'prepare_registration' => $this->prepare($arguments),
            'confirm_registration' => $this->confirm($arguments, $request),
            default => throw new \DomainException('Outil inconnu : ' . $name),
        };
    }

    private function listEvents() : array
    {
        $coming = array_filter($this->calendar->all(), fn (array $soiree) => !$soiree['past']);
        return [
            'next' => $this->calendar->getNext()['date'] ?? null,
            'events' => array_values(array_map([$this->calendar, 'toApi'], $coming)),
        ];
    }

    private function getEvent(array $arguments) : array
    {
        return $this->calendar->toApi($this->findSoiree(self::text($arguments, 'event_id')));
    }

    private function prepare(array $arguments) : array
    {
        $date = self::text($arguments, 'event_id');
        $soiree = $date === '' ? $this->calendar->getNext() : $this->findSoiree($date);
        if ($soiree === null) {
            throw new \DomainException('Aucune soirée n\'est annoncée pour le moment.');
        }
        $this->assertOpen($soiree);
        $firstname = self::name($arguments, 'firstname', 'prénom');
        $lastname = self::name($arguments, 'lastname', 'nom');
        $expires = time() + self::TOKEN_LIFETIME;
        return [
            'status' => 'preview',
            'event' => [
                'id' => $soiree['date'],
                'name' => $soiree['name'],
                'summary' => $soiree['summary'],
                'url' => $soiree['url'],
            ],
            'participant' => ['firstname' => $firstname, 'lastname' => $lastname],
            'prices' => $this->calendar->toApi($soiree)['prices'],
            'requirements' => 'Carte d\'identité ou d\'étudiant obligatoire à l\'entrée.',
            'terms' => Calendar::SITE . '/legal',
            'confirmation_token' => self::TOKEN_PREFIX . self::encode([
                'event' => $soiree['date'],
                'firstname' => $firstname,
                'lastname' => $lastname,
                'expires' => $expires,
                'nonce' => bin2hex(random_bytes(16)),
            ]),
            'expires_at' => (new \DateTimeImmutable('@' . $expires))
                ->setTimezone(new \DateTimeZone(Calendar::TIMEZONE))
                ->format('c'),
            'next_step' => 'Rien n\'est encore enregistré. Montre ce résumé et le R.O.I. à la personne, puis appelle '
                . 'confirm_registration seulement si elle dit oui.',
        ];
    }

    private function confirm(array $arguments, ServerRequestInterface $request) : array
    {
        $data = self::decode(self::text($arguments, 'confirmation_token'));
        if ($data['expires'] < time()) {
            throw new \DomainException('Ce résumé a expiré : appelle à nouveau prepare_registration.');
        }
        // The token isn't signed: it only makes the assistant go through the preview, so check it all again
        $firstname = self::name($data, 'firstname', 'prénom');
        $lastname = self::name($data, 'lastname', 'nom');
        $soiree = $this->findSoiree($data['event']);
        $this->assertOpen($soiree);
        if ($this->spamGuard->isTooMany($request, 'mcp-sign-up', self::MAX_SIGN_UPS, self::MAX_SIGN_UPS_DELAY)) {
            throw new \DomainException('Trop d\'inscriptions depuis cette adresse : réessaie dans une heure.');
        }
        $this->spamGuard->sent($request, 'mcp-sign-up', self::MAX_SIGN_UPS_DELAY);

        $eventId = (int) $soiree['event']->id;
        // Sent twice, or already signed up on the form: on the list once
        if (!$this->isOnList($firstname, $lastname, $eventId)) {
            $this->dancers->insert(['firstname' => $firstname, 'lastname' => $lastname, 'event_id' => $eventId]);
        }
        return [
            'status' => 'confirmed',
            'event' => ['id' => $soiree['date'], 'name' => $soiree['name'], 'summary' => $soiree['summary'], 'url' => $soiree['url']],
            'participant' => ['firstname' => $firstname, 'lastname' => $lastname],
            'note' => 'Ce nom est sur la liste d\'entrée. On paie à l\'entrée. Pour annuler, écris à l\'équipe sur '
                . Calendar::SITE . '/contact.',
        ];
    }

    private function findSoiree(string $date) : array
    {
        $soiree = $this->calendar->find($date);
        if ($soiree === null) {
            throw new \DomainException('Aucune soirée le ' . $date . ' : list_events donne les dates.');
        }
        return $soiree;
    }

    /**
     * The same rule as the form: only the next soirée, while its sign-ups are open
     */
    private function assertOpen(array $soiree) : void
    {
        $registration = $soiree['registration'];
        if ($registration['status'] === Calendar::REGISTRATION_OPEN && $soiree['next'] && $soiree['event'] !== null) {
            return;
        }
        $message = 'Les inscriptions à cette soirée ne sont pas ouvertes (état : ' . $registration['status'] . ').';
        if ($registration['status'] === Calendar::REGISTRATION_UPCOMING) {
            $message .= ' Elles ouvrent le ' . $registration['opens']->format('c') . '.';
        }
        throw new \DomainException($message);
    }

    private function isOnList(string $firstname, string $lastname, int $eventId) : bool
    {
        try {
            $this->dancers->findDuplicates($firstname, $lastname, $eventId);
            return true;
        } catch (NoRecordException $e) {
            return false;
        }
    }

    private static function text(array $values, string $key) : string
    {
        return is_string($values[$key] ?? null) ? trim($values[$key]) : '';
    }

    private static function name(array $values, string $key, string $label) : string
    {
        $name = self::text($values, $key);
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/\p{Cc}/u', $name)) {
            throw new \DomainException('Il faut un ' . $label . ' de 1 à 100 caractères.');
        }
        return $name;
    }

    private static function encode(array $data) : string
    {
        return rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    /**
     * @return array{event: string, firstname: string, lastname: string, expires: int}
     */
    private static function decode(string $token) : array
    {
        $data = null;
        if (str_starts_with($token, self::TOKEN_PREFIX)) {
            $json = base64_decode(strtr(substr($token, strlen(self::TOKEN_PREFIX)), '-_', '+/'), true);
            $data = is_string($json) ? json_decode($json, true) : null;
        }
        if (!is_array($data) || !is_string($data['event'] ?? null) || !is_int($data['expires'] ?? null)
            || !is_string($data['nonce'] ?? null) || strlen($data['nonce']) !== 32) {
            throw new \DomainException('Ce jeton n\'est pas valide : appelle à nouveau prepare_registration.');
        }
        return $data;
    }
}
