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
            new \Twig\TwigFilter('lazyImages', [$this, 'lazyImages']),
            new \Twig\TwigFilter('withoutEmptyParagraphs', [$this, 'withoutEmptyParagraphs'])
        ];
    }

    /**
     * Removes the blank paragraphs (<p>&nbsp;</p>) the admin editor leaves when pressing Enter several times
     * @param string|null $content
     * @return string
     */
    public function withoutEmptyParagraphs(?string $content) : string
    {
        return preg_replace('#<p\b[^>]*>(?:\s|&nbsp;|\x{a0}|<br\s*/?>)*</p>\s*#iu', '', $content ?? '');
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
     * Adds loading="lazy" to the <img> tags of an HTML string, except the first $eager ones,
     * and a srcset of the smaller copies made by Upload::generateWidths when they exist
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
        return preg_replace_callback('/<img\b[^>]*>/i', function ($match) use (&$count, $eager) {
            $tag = $this->addSrcset($match[0]);
            if (preg_match('/\bloading=/i', $tag) || $count++ < $eager) {
                return $tag;
            }
            return substr_replace($tag, '<img loading="lazy" decoding="async"', 0, 4);
        }, $content);
    }

    /**
     * Lets the browser download a copy of the image that fits its displayed width instead of the full photo.
     * Only for images with a width attribute: without one, the srcset would change their displayed size
     */
    private function addSrcset(string $tag) : string
    {
        if (preg_match('/\bsrcset=/i', $tag)
            || !preg_match('/\swidth=["\']?(\d+)/i', $tag, $width)
            // Images of this site only: /images/..., ../../images/... (what the editor inserts) or https://rockfever.be/images/...
            || !preg_match('/\ssrc=(["\'])(?:https?:\/\/(?:www\.)?rockfever\.be)?[.\/]*images\/([^"\'?#]+)\1/i', $tag, $src)
        ) {
            return $tag;
        }
        $path = rawurldecode($src[2]);
        $file = dirname(__DIR__, 3) . '/www/images/' . $path;
        if (str_contains($path, '..') || !is_file($file)) {
            return $tag;
        }
        // Spaces or commas in a file name would split a srcset entry
        $url = fn (string $path) => '/images/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        $candidates = [];
        $info = pathinfo($file);
        $pattern = preg_replace('/[*?\[\]\\\\]/', '\\\\$0', $info['dirname'] . '/sizes/' . $info['filename']) . '-*.webp';
        foreach (glob($pattern) ?: [] as $copy) {
            $size = substr(pathinfo($copy, PATHINFO_FILENAME), strlen($info['filename']) + 1);
            if (ctype_digit($size)) {
                $candidates[(int) $size] = $url(dirname($path) . '/sizes/' . basename($copy)) . ' ' . $size . 'w';
            }
        }
        if (!$candidates) {
            return $tag;
        }
        ksort($candidates);
        [$originalWidth] = getimagesize($file) ?: [0];
        if ($originalWidth > max(array_keys($candidates))) {
            $candidates[$originalWidth] = $url($path) . ' ' . $originalWidth . 'w';
        }
        return substr_replace(
            $tag,
            sprintf(
                '<img srcset="%s" sizes="(max-width: %dpx) 100vw, %dpx"',
                implode(', ', $candidates),
                $width[1],
                $width[1]
            ),
            0,
            4
        );
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
