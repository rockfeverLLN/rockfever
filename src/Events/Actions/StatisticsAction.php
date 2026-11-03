<?php

namespace App\Events\Actions;

use App\Events\Statistics;
use Framework\Renderer\RendererInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The club's statistics over every past soirée, or over one season (?saison=2025)
 */
class StatisticsAction
{
    public function __construct(private RendererInterface $renderer, private Statistics $statistics)
    {
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $seasons = $this->statistics->seasons();
        $season = $request->getQueryParams()['saison'] ?? null;
        $season = is_numeric($season) && isset($seasons[(int)$season]) ? (int)$season : null;

        return $this->renderer->render(
            '@events/admin/club-statistics',
            ['seasons' => $seasons, 'season' => $season] + $this->statistics->compute($season)
        );
    }
}
