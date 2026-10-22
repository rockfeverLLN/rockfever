<?php

namespace Framework\Twig;

/**
 * Adds a version made from the file's content to the URL of a file in www/: asset('/css/style.css') gives
 * /css/style.css?v=1a2b3c4d. Browsers keep CSS and JS for a year (www/.htaccess): a new version makes them
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
            new \Twig\TwigFunction('asset', [$this, 'asset']),
            new \Twig\TwigFunction('assetExists', [$this, 'assetExists'])
        ];
    }

    public function asset(string $path) : string
    {
        if (!isset($this->urls[$path])) {
            $file = $this->file($path);
            $this->urls[$path] = is_file($file) ? $path . '?v=' . hash_file('crc32b', $file) : $path;
        }
        return $this->urls[$path];
    }

    /**
     * For the files only the deploy builds (bin/critical-css.php), which a fresh checkout doesn't have
     */
    public function assetExists(string $path) : bool
    {
        return is_file($this->file($path));
    }

    private function file(string $path) : string
    {
        return dirname(__DIR__, 3) . '/www' . $path;
    }
}
