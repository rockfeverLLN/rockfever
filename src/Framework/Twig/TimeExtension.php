<?php
/**.
 * User: Nicolas van de Walle
 * Date: 12-Feb-18
 * Time: 17:35
 */

namespace Framework\Twig;

class TimeExtension extends \Twig\Extension\AbstractExtension
{
    private $months = [
        'janvier',
        'février',
        'mars',
        'avril',
        'mai',
        'juin',
        'juillet',
        'août',
        'septembre',
        'octobre',
        'novembre',
        'décembre'
    ];

    /**
     * @return \Twig\TwigFilter[]
     */
    public function getFilters() : array
    {
        return [
            new \Twig\TwigFilter('dateToText', [$this, 'dateToText']),
            new \Twig\TwigFilter('militaryToTime', [$this, 'militaryToTime'])
        ];
    }

    public function dateToText(?string $date) : string
    {
        $date = explode('/', $date);
        return $date[0] . ' ' . $this->months[$date[1]-1] . ' ' . $date[2];
    }

    public function militaryToTime($time)
    {
        return substr($time, 0, 2) . ':' . substr($time, 2, 2);
    }
}
