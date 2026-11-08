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
            new \Twig\TwigFilter('toSearch', [$this, 'toSearch']),
            new \Twig\TwigFilter('statusToText', [$this, 'statusToText'], [
                'is_safe' => ['html']
            ]),
            new \Twig\TwigFilter('round', [$this, 'round']),
            new \Twig\TwigFilter('lazyImages', [$this, 'lazyImages']),
            new \Twig\TwigFilter('withoutEmptyParagraphs', [$this, 'withoutEmptyParagraphs']),
            new \Twig\TwigFilter('typography', [$this, 'typography'], ['is_safe' => ['html']])
        ];
    }

    /**
     * French typography for the text written in the admin, so every page follows the same rules: a no-break
     * space before : and inside « », a narrow one before ; ! ? (added when missing before ! and ?), and
     * apostrophes as ’. Also drops the "�" left by characters a database couldn't store.
     * Only the text changes, never the tags, comments, scripts or styles. Takes HTML: escape plain text first
     * (description|e|typography)
     * @param string|null $content
     * @return string
     */
    public function typography(?string $content) : string
    {
        $parts = preg_split(
            '#(<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>|<[^>]*>)#is',
            $content ?? '',
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        foreach ($parts as $i => $part) {
            // Odd parts are the tags
            if ($i % 2 === 1 || trim($part) === '') {
                continue;
            }
            $text = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace("\u{FFFD}", '', $text);
            $text = preg_replace("/(?<=\p{L})'(?=\p{L})/u", '’', $text);
            // "personnes!" gets its space, "page.php?id=1" doesn't
            $text = preg_replace('/(?<=[\p{L}\d)])(?=[!?]+(?:\s|$|[)»"]))/u', "\u{202F}", $text);
            $text = preg_replace('/[ \x{A0}\x{202F}]+(?=[!?;])/u', "\u{202F}", $text);
            $text = preg_replace('/[ \x{A0}\x{202F}]+(?=:)/u', "\u{A0}", $text);
            $text = preg_replace(['/«[ \x{A0}\x{202F}]*/u', '/[ \x{A0}\x{202F}]*»/u'], ["«\u{A0}", "\u{A0}»"], $text);
            $parts[$i] = htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        }
        return implode('', $parts);
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

    /**
     * For a search URL: "AC/DC & co" becomes "AC%2FDC+%26+co", where a "&" or "#" would have cut the query
     */
    public function toSearch(?string $title) : string
    {
        return urlencode(trim(preg_replace('/\s+/', ' ', (string) $title)));
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
     * @param string|null $sizes The width the images are shown at, as a sizes attribute, when not the home column's
     * @return string
     */
    public function lazyImages(?string $content, int $eager = 0, ?string $sizes = null) : string
    {
        if (is_null($content)) {
            return '';
        }
        $count = 0;
        return preg_replace_callback('/<img\b[^>]*>/i', function ($match) use (&$count, $eager, $sizes) {
            $tag = $this->addSrcset($this->cleanSrc($this->pastedToFile($match[0])), $sizes);
            if (preg_match('/\bloading=/i', $tag) || $count++ < $eager) {
                return $tag;
            }
            return substr_replace($this->addAspectRatio($tag), '<img loading="lazy" decoding="async"', 0, 4);
        }, $content);
    }

    /**
     * Writes the width and height attributes as a CSS aspect-ratio. The stylesheet's height: auto overrides the
     * height attribute, and Chrome then reports the lazy image as unsized: it would take no room until loaded
     */
    private function addAspectRatio(string $tag) : string
    {
        if (!preg_match('/\swidth=["\']?(\d+)/i', $tag, $width)
            || !preg_match('/\sheight=["\']?(\d+)/i', $tag, $height)
            || (int) $width[1] === 0 || (int) $height[1] === 0
        ) {
            return $tag;
        }
        $ratio = sprintf('aspect-ratio: %d / %d;', $width[1], $height[1]);
        if (preg_match('/\sstyle=(["\'])(.*?)\1/is', $tag, $style, PREG_OFFSET_CAPTURE)) {
            if (stripos($style[2][0], 'aspect-ratio') !== false) {
                return $tag;
            }
            $value = rtrim(trim($style[2][0]), ';');
            return substr_replace($tag, ($value === '' ? '' : $value . '; ') . $ratio, $style[2][1], strlen($style[2][0]));
        }
        return substr_replace($tag, sprintf('<img style="%s"', $ratio), 0, 4);
    }

    /**
     * Points an image pasted into the editor, which keeps it in the text as a base64 data: URL, to a copy of it in
     * www/images/about/pasted/ (named after its content, saved the first time). Otherwise every visit downloads it
     * inside the page, a third bigger, before anything else, and no browser cache keeps it
     */
    private function pastedToFile(string $tag) : string
    {
        return preg_replace_callback(
            '/(\ssrc=)(["\'])data:image\/[a-z]+;base64,([A-Za-z0-9+\/=\s]+)\2/i',
            function ($match) {
                $data = base64_decode($match[3], true);
                $type = $data === false ? false : getimagesizefromstring($data);
                // Pictures only, saved under the extension of what they really are
                $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
                if (!$type || !isset($extensions[$type[2]])) {
                    return $match[0];
                }
                $path = 'about/pasted/' . substr(hash('sha256', $data), 0, 20) . '.' . $extensions[$type[2]];
                $file = dirname(__DIR__, 3) . '/www/images/' . $path;
                if (!is_file($file)) {
                    // Written aside then renamed, so a visitor never gets half a file. If it can't be saved, the
                    // image stays in the page
                    $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.part';
                    if ((!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true))
                        || @file_put_contents($temporary, $data) !== strlen($data)
                        || !@rename($temporary, $file)
                    ) {
                        @unlink($temporary);
                        return $match[0];
                    }
                }
                return $match[1] . $match[2] . self::imageUrl($path) . $match[2];
            },
            $tag,
            1
        );
    }

    /**
     * Writes the src of this site's images as /images/... with its spaces encoded. The editor inserts
     * ../../images/photo staff.jpg: invalid HTML, and link checkers resolve it to a page that doesn't exist
     */
    private function cleanSrc(string $tag) : string
    {
        return preg_replace_callback(
            '/(\ssrc=)(["\'])(?:https?:\/\/(?:www\.)?rockfever\.be)?[.\/]*images\/([^"\'?#]+)\2/i',
            function ($match) {
                $path = rawurldecode(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5));
                return str_contains($path, '..') ? $match[0] : $match[1] . $match[2] . self::imageUrl($path) . $match[2];
            },
            $tag,
            1
        );
    }

    /**
     * URL of a file of www/images/. Spaces or commas in a file name would split a srcset entry
     */
    private static function imageUrl(string $path) : string
    {
        return '/images/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Lets the browser download a copy of the image that fits its displayed width instead of the full photo.
     * Only for images with a width attribute: without one, the srcset would change their displayed size
     */
    private function addSrcset(string $tag, ?string $sizes = null) : string
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
        $url = fn (string $path) => self::imageUrl($path);
        $candidates = [];
        $info = pathinfo($file);
        $pattern = preg_replace('/[*?\[\]\\\\]/', '\\\\$0', $info['dirname'] . '/sizes/' . $info['filename']) . '-*.jpg';
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
                '<img srcset="%s" sizes="%s"',
                implode(', ', $candidates),
                $sizes ?? self::columnSizes((int) $width[1])
            ),
            0,
            4
        );
    }

    /**
     * Width of the home page's column (.home-content in www/css/style.css) by window width, as
     * [up to this window width, share of the window, plus pixels]: the container's 20px gutters on phones,
     * 85% then 70% of the window (.container in base.css), at most 880px
     */
    private const COLUMN = [[600, 1, -40], [992, 0.85, 0], [1257, 0.7, 0], [null, 0, 880]];

    /**
     * The sizes attribute of an image of the column: the width it is shown at, the column's but no wider than its
     * width attribute. Telling the browser 100vw made it pick a copy for the whole window, gutters included: the
     * 960px one for a photo shown 744 device pixels wide on a phone
     */
    private static function columnSizes(int $width) : string
    {
        $entries = [];
        $add = function (?int $upTo, string $size) use (&$entries) {
            // A size the same as the one before covers its range too
            if ($entries && end($entries)[1] === $size) {
                array_pop($entries);
            }
            $entries[] = [$upTo, $size];
        };
        $from = 0;
        foreach (self::COLUMN as [$upTo, $share, $pixels]) {
            if ($share === 0) {
                $add($upTo, $pixels <= $width ? $pixels . 'px' : $width . 'px');
                continue;
            }
            $column = $pixels < 0 ? sprintf('calc(%dvw - %dpx)', $share * 100, -$pixels) : round($share * 100) . 'vw';
            // Window width from which the column is wider than the image
            $wider = ($width - $pixels) / $share;
            if ($wider >= $upTo) {
                $add($upTo, $column);
            } elseif ($wider <= $from) {
                $add($upTo, $width . 'px');
            } else {
                $add((int) floor($wider), $column);
                $add($upTo, $width . 'px');
            }
            $from = $upTo;
        }
        $last = array_pop($entries);
        $sizes = array_map(fn ($entry) => sprintf('(max-width: %dpx) %s', $entry[0], $entry[1]), $entries);
        $sizes[] = $last[1];
        return implode(', ', $sizes);
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
