<?php
/**.
 * Author: Nicolas van de Walle
 * Website: http://www.nicwalle.com
 * Date: 29-Mar-18
 */

namespace App\Main\Actions;

use App\Main\Calendar;
use App\Main\Table\AboutTable;
use Framework\Renderer\RendererInterface;

class HomeAction
{
    protected $viewPath = '@main/home';

    protected $routePrefix = 'home';

    private const DATES = '[dates]';
    /**
     * @var AboutTable
     */
    private $table;
    /**
     * @var RendererInterface
     */
    private $renderer;

    public function __construct(RendererInterface $renderer, AboutTable $table, private Calendar $calendar)
    {
        $this->table = $table;
        $this->renderer = $renderer;
    }

    public function __invoke()
    {
        // The sections after the one titled "Le Staff" each present a member: the page shows them side by side as cards
        $items = [];
        $members = [];
        $staffFound = false;
        foreach ($this->table->findAll() as $item) {
            if ($this->isEmpty($item->content) && trim($item->title) === '') {
                continue;
            }
            if ($staffFound) {
                $members[] = $item;
                continue;
            }
            $items[] = $item;
            $staffFound = mb_strtolower(trim($item->title)) === 'le staff';
        }

        // A "[dates]" paragraph in a section becomes the soirées of the quadrimestre under way (planned in the admin),
        // marked past or next like on /calendrier, so it never needs editing when a soirée is over
        $dates = null;
        foreach ($items as $item) {
            if (str_contains($item->content, self::DATES)) {
                $dates ??= $this->renderer->render('@main/soireesList', [
                    'semesters' => $this->calendar->getSemesters(),
                    'nextSemester' => $this->calendar->getNextSemester(),
                    'place' => $this->calendar->getPlace(),
                ]);
                $item->content = preg_replace_callback(
                    '#<p>\s*' . preg_quote(self::DATES, '#') . '\s*</p>|' . preg_quote(self::DATES, '#') . '#',
                    fn () => $dates,
                    $item->content
                );
            }
        }

        // The coming soirées, marked up as events in the page's head and featured under the header
        return $this->renderer->render($this->viewPath, compact('items', 'members') + [
            'soirees' => $this->calendar->getSoirees(),
            'place' => $this->calendar->getPlace(),
            'address' => $this->calendar->getAddress(),
        ]);
    }

    /**
     * True when a section shows nothing: only blank paragraphs, comments (e.g. a disabled video) or spaces
     */
    private function isEmpty(string $content) : bool
    {
        $visible = strip_tags($content, '<img><iframe><video>');
        return trim(str_replace(["\u{a0}", '&nbsp;'], ' ', $visible)) === '';
    }
}
