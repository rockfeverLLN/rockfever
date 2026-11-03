<?php

namespace Framework\Twig;

/**
 * URL of a JavaScript library's file on jsDelivr, at the version in package.json: cdn('tinymce', 'tinymce.min.js')
 * gives https://cdn.jsdelivr.net/npm/tinymce@8.10.1/tinymce.min.js. Dependabot raises the versions in package.json
 * (.github/dependabot.yml), so the templates never hold a version number
 */
class CdnExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @var array<string, string>|null package => version, read once per request
     */
    private $versions;

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions() : array
    {
        return [
            new \Twig\TwigFunction('cdn', [$this, 'cdn'])
        ];
    }

    public function cdn(string $package, string $file) : string
    {
        if ($this->versions === null) {
            $json = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/package.json'), true);
            $this->versions = $json['dependencies'] ?? [];
        }
        if (!isset($this->versions[$package])) {
            throw new \InvalidArgumentException("$package isn't in package.json");
        }
        return 'https://cdn.jsdelivr.net/npm/' . $package . '@' . $this->versions[$package] . '/' . $file;
    }
}
