<?php

namespace Framework\Twig;

/**
 * Adds a version made from the file's content to the URL of a file in www/: asset('/css/style.css') gives
 * /css/style.css?v=1a2b3c4d. Browsers keep CSS and JS for a week (www/.htaccess): a new version makes them
 * download the file again as soon as it changes, instead of mixing an old stylesheet with new pages
 */
class AssetExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @var string[] path => versioned URL, for the files already hashed during this request
     */
    private $urls = [];

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions() : array
    {
        return [
            new \Twig\TwigFunction('asset', [$this, 'asset'])
        ];
    }

    /**
     * @param string|null $fallback file to use when $path doesn't exist, e.g. a file only built by the deploy
     */
    public function asset(string $path, ?string $fallback = null) : string
    {
        if (!isset($this->urls[$path])) {
            $file = dirname(__DIR__, 3) . '/www' . $path;
            if (!is_file($file) && $fallback !== null) {
                return $this->asset($fallback);
            }
            $this->urls[$path] = is_file($file) ? $path . '?v=' . hash_file('crc32b', $file) : $path;
        }
        return $this->urls[$path];
    }
}
