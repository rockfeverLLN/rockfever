<?php
namespace App\Main;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * setting('FACEBOOK_URL') in the templates: a setting of the admin (App\Main\Settings)
 */
class SettingsExtension extends AbstractExtension
{
    public function __construct(private Settings $settings)
    {
    }

    public function getFunctions() : array
    {
        return [new TwigFunction('setting', [$this->settings, 'get'])];
    }
}
