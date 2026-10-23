<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 03-Mar-18
 */

namespace App\Events\Entity;

#[\AllowDynamicProperties]
class Event
{

    public $id;

    public $title;

    public $description;

    public $date;

    public $waltzTime;

    public $rockTime;

    public $place;

    public $price;

    public $facebookLink;

    public $photosPassword;

    public $participantsCount;

    // Sign-ups open on their own at midnight, this many days before the soirée (unless closed in the admin)
    const REGISTRATION_DAYS = 7;

    const TIMEZONE = 'Europe/Brussels';

    public function setDate(string $date)
    {
        $this->date = preg_replace("/([\d]{4})\-([\d]{2})\-([\d]{2})/", "$3/$2/$1", $date) ?? null;
    }

    /**
     * When sign-ups open for a soirée on that day (Y-m-d)
     */
    public static function registrationOpeningFor(string $date) : \DateTimeImmutable
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone(self::TIMEZONE)))
            ->setTime(0, 0)
            ->modify('-' . self::REGISTRATION_DAYS . ' days');
    }

    /**
     * The day of the event as Y-m-d ($date is d/m/Y)
     */
    public function getDay() : string
    {
        return implode('-', array_reverse(explode('/', $this->date)));
    }

    public function getRegistrationOpening() : \DateTimeImmutable
    {
        return self::registrationOpeningFor($this->getDay());
    }

    /**
     * Not closed in the admin, and the opening day is reached. findNext() only gives events of today or later
     */
    public function isRegistrationOpen(?\DateTimeImmutable $now = null) : bool
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        return empty($this->closed) && $now >= $this->getRegistrationOpening();
    }

    /**
     * Sign-ups that will open on their own later (the event is announced early)
     */
    public function isRegistrationUpcoming(?\DateTimeImmutable $now = null) : bool
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        return empty($this->closed) && $now < $this->getRegistrationOpening();
    }

    /**
     * @param string $waltzTime
     */
    public function setWaltzTime(string $waltzTime)
    {
        $this->waltzTime = $this->getHoursMinutesTime($waltzTime);
    }

    /**
     * @param string $rockTime
     */
    public function setRockTime(string $rockTime)
    {
        $this->rockTime = $this->getHoursMinutesTime($rockTime);
    }

    public function setFacebookLink(string $facebookLink)
    {
        $this->facebookLink = preg_replace(
            '/(https?:\/\/)?(www\.)?facebook.com\/events\/([a-zA-Z0-9]+)/',
            'https://www.facebook.com/events/$3',
            $facebookLink
        );
    }

    /**
     * @param null|string $time
     * @return null|string
     */
    private function getHoursMinutesTime(?string $time) : ?string
    {
        $explodedTime = explode(':', $time);
        if (count($explodedTime)>=2) {
            return $explodedTime[0] . ':' . $explodedTime[1];
        } else {
            return null;
        }
    }
}
