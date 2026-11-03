<?php
namespace App\Main;

use App\Main\Table\ValueTable;

/**
 * Every setting of the site in one place, each one a row of the values table that the admin edits: the soirées' on
 * /admin/events (group "soirees"), the others on Paramètres > Réglages (/admin/values). A setting without its row
 * (never saved yet) has its default, so a new one needs no SQL: saving it in the admin adds the row
 */
class Settings
{
    // name => label, type (time, string, int, email, url, html), default, group
    const DEFINITIONS = [
        'SOIREE_WALTZ_TIME' => ['Heure de la valse', 'time', '20:30', 'soirees'],
        'SOIREE_ROCK_TIME' => ['Heure du rock', 'time', '21:00', 'soirees'],
        'SOIREE_END_TIME' => ['Heure de fin (le lendemain si avant le début)', 'time', '01:00', 'soirees'],
        'SOIREE_PLACE' => ['Lieu', 'string', 'Hall Sainte-Barbe', 'soirees'],
        'SOIREE_ADDRESS' => ['Adresse', 'string', 'Place Sainte-Barbe 1, 1348 Louvain-la-Neuve', 'soirees'],
        'PRICE_ONLINE' => ['Prix avec inscription sur le site (€)', 'int', '3', 'soirees'],
        'PRICE_DOOR' => ['Prix sans inscription (€)', 'int', '4', 'soirees'],
        'REGISTRATION_DAYS' => ['Ouverture des inscriptions (jours avant, à minuit)', 'int', '7', 'soirees'],
        'CONTACT_EMAIL' => ['Adresse qui reçoit les messages du formulaire de contact', 'email', '', 'site'],
        'FACEBOOK_URL' => ['Page Facebook', 'url', 'https://www.facebook.com/rockfeverlln', 'site'],
        'INSTAGRAM_URL' => ['Compte Instagram', 'url', 'https://www.instagram.com/rockfeverlln/', 'site'],
        'ROI' => ['Règlement d\'ordre intérieur', 'html', '', 'site'],
        'TERMS' => ['Conditions générales', 'html', '', 'site'],
    ];

    /**
     * @var array<string, string>|null the saved values, once read
     */
    private ?array $values = null;

    public function __construct(private ValueTable $table)
    {
    }

    public function get(string $name) : string
    {
        if ($this->values === null) {
            try {
                $this->values = $this->table->findValues();
            } catch (\PDOException) {
                // The error pages show the defaults rather than failing again
                $this->values = [];
            }
        }
        return $this->values[$name] ?? self::DEFINITIONS[$name][2];
    }

    public function getInt(string $name) : int
    {
        return (int) $this->get($name);
    }

    /**
     * The settings of a group, each with its label, type and value
     * @return array[] name => ['label' => …, 'type' => …, 'value' => …]
     */
    public function group(string $group) : array
    {
        $settings = [];
        foreach (self::DEFINITIONS as $name => [$label, $type, , $of]) {
            if ($of === $group) {
                $settings[$name] = ['label' => $label, 'type' => $type, 'value' => $this->get($name)];
            }
        }
        return $settings;
    }

    /**
     * Checks then saves the given settings (name => value); returns the errors (name => message), nothing saved if any
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    public function save(array $values) : array
    {
        $clean = [];
        $errors = [];
        foreach ($values as $name => $value) {
            if (!isset(self::DEFINITIONS[$name])) {
                continue;
            }
            [$label, $type] = self::DEFINITIONS[$name];
            $value = trim((string) $value);
            $error = self::check($type, $value);
            if ($error !== null) {
                $errors[$name] = $label . ' : ' . $error;
                continue;
            }
            $clean[$name] = $type === 'int' ? (string) (int) $value : $value;
        }
        if ($errors) {
            return $errors;
        }
        foreach ($clean as $name => $value) {
            $this->table->setValue($name, $value, self::DEFINITIONS[$name][0], self::DEFINITIONS[$name][1]);
        }
        $this->values = null;
        return [];
    }

    private static function check(string $type, string $value) : ?string
    {
        return match ($type) {
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? null : 'une heure comme 20:30',
            'int' => preg_match('/^\d{1,3}$/', $value) ? null : 'un nombre entier',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'une adresse e-mail',
            'url' => $value === '' || preg_match('#^https://#', $value) ? null : 'un lien qui commence par https://',
            'string' => $value !== '' && mb_strlen($value) <= 250 ? null : 'entre 1 et 250 caractères',
            default => null,
        };
    }
}
