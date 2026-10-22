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
            new \Twig\TwigFilter('ago', [$this, 'ago'], ['is_safe'=>['html']]),
            new \Twig\TwigFilter('dateToText', [$this, 'dateToText']),
            new \Twig\TwigFilter('militaryToTime', [$this, 'militaryToTime'])
        ];
    }

    public function ago($date, string $format = "d/m/Y H:i")
    {

        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        if (is_null($date)) {
            return "";
        }
        return '<span class="timeago" datetime="'.$date->format(\DateTime::ISO8601).'">'
            . $date->format($format)
            .'</span>';
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
