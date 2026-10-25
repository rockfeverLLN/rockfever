<?php
namespace App\Main\Actions;

use App\Main\Calendar;
use GuzzleHttp\Psr7\Response;

/**
 * /sitemap.xml: the public pages and the page of each soirée of /calendrier still to come, so it never needs editing.
 * /music and the past soirées are left out: their pages are noindex
 */
class SitemapAction
{
    private const PAGES = ['/', '/event', '/calendrier', '/contact', '/legal'];

    public function __construct(private Calendar $calendar)
    {
    }

    public function __invoke()
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('    ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach (self::PAGES as $page) {
            $xml->startElement('url');
            $xml->writeElement('loc', Calendar::SITE . $page);
            $xml->endElement();
        }
        foreach ($this->calendar->getSoirees() as $soiree) {
            if ($soiree['past']) {
                continue;
            }
            $xml->startElement('url');
            $xml->writeElement('loc', $soiree['url']);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();
        return new Response(200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ], $xml->outputMemory());
    }
}
