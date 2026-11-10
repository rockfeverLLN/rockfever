<?php
namespace App\Main\Actions;

use App\Main\Calendar;
use Framework\Renderer\RendererInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * /calendrier: the dates of the soirées, /calendrier/2026-11-11: the page of one of them (its canonical URL),
 * /calendrier.ics: all of them for a calendar app, /calendrier/2026-11-11.ics: one of them
 */
class CalendarAction
{
    public function __construct(private RendererInterface $renderer, private Calendar $calendar)
    {
    }

    public function __invoke(Request $request)
    {
        $path = $request->getUri()->getPath();
        if (str_ends_with($path, '.ics')) {
            $date = $request->getAttribute('date');
            if ($date !== null && !$this->calendar->has($date)) {
                return new Response(404, [], 'Pas de soirée à cette date');
            }
            $filename = 'rock-fever' . ($date ? '-' . $date : '') . '.ics';
            return new Response(200, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                // One soirée is a file to open in the calendar app; the full list is also read by subscriptions
                'Content-Disposition' => ($date ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
                'Cache-Control' => 'public, max-age=3600',
                // A file for calendar apps, not a page for search results
                'X-Robots-Tag' => 'noindex',
            ], $this->calendar->toIcs($date));
        }
        $date = $request->getAttribute('date');
        if ($date !== null) {
            $soiree = $this->calendar->find($date);
            if ($soiree === null) {
                return new Response(404, [], $this->renderer->render('404'));
            }
            return $this->renderer->render('@main/soireePage', compact('soiree'));
        }
        return $this->renderer->render('@main/calendar', [
            'semesters' => $this->calendar->getSemesters(),
            'nextSemester' => $this->calendar->getNextSemester(),
            'place' => $this->calendar->getPlace(),
            'address' => $this->calendar->getAddress(),
            'prices' => $this->calendar->getPrices(),
        ]);
    }
}
