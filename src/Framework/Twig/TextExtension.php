<?php
/**.
 * User: Nicolas van de Walle
 * Date: 12-Feb-18
 * Time: 17:04
 */

namespace Framework\Twig;

class TextExtension extends \Twig\Extension\AbstractExtension
{

    /**
     * @return \Twig\TwigFilter[]
     */
    public function getFilters() : array
    {
        return [
            new \Twig\TwigFilter('excerpt', [$this, 'excerpt']),
            new \Twig\TwigFilter('removeSpecialChars', [$this, 'removeSpecialChars']),
            new \Twig\TwigFilter('toSearch', [$this, 'toSearch']),
            new \Twig\TwigFilter('statusToText', [$this, 'statusToText'], [
                'is_safe' => ['html']
            ]),
            new \Twig\TwigFilter('round', [$this, 'round']),
            new \Twig\TwigFilter('lazyImages', [$this, 'lazyImages'])
        ];
    }

    /**
     * @param string $content
     * @param int $maxLength
     * @return string
     */
    public function excerpt(?string $content, int $maxLength = 100) : string
    {
        if (is_null($content)) {
            return '';
        }
        $content = trim(preg_replace("/<[^>]*>/", '', $content));
        if (mb_strlen($content) > $maxLength) {
            $excerpt = mb_substr($content, 0, $maxLength);
            $lastSpace = mb_strrpos($excerpt, ' ');
            return mb_substr($excerpt, 0, $lastSpace) . '...';
        }
        return $content;
    }

    public function removeSpecialChars(?string $content) : string
    {
        $content = preg_replace('/\'/', '', $content);
        $content = preg_replace('/\"/', '', $content);

        return $content;
    }

    public function toSearch(?string $title)
    {
        return preg_replace("/\s+/", "+", $title);
    }

    public function round(float $value, int $precision = 2)
    {

        return round($value, $precision);
    }

    /**
     * Adds loading="lazy" to the <img> tags of an HTML string, except the first $eager ones
     * @param string|null $content
     * @param int $eager
     * @return string
     */
    public function lazyImages(?string $content, int $eager = 0) : string
    {
        if (is_null($content)) {
            return '';
        }
        $count = 0;
        return preg_replace_callback('/<img\b(?![^>]*\bloading=)/i', function ($match) use (&$count, $eager) {
            return $count++ < $eager ? $match[0] : $match[0] . ' loading="lazy" decoding="async"';
        }, $content);
    }

    public function statusToText(int $status)
    {
        $statusToText = [
            "<i class='material-icons'>check_box_outline_blank</i>",
            "<i class='material-icons green-text'>check_box</i>",
            "<i class='material-icons amber-text'>star</i>",
            "<i class='material-icons'>exposure_plus_1</i>"
        ];
        return $statusToText[$status];
    }
}
