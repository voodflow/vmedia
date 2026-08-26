<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Voodflow\Vmedia\Support\MediaUsage;

class MediaStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('vmedia::admin.stats.heading');
    }

    protected function getStats(): array
    {
        $stats = MediaUsage::stats();
        $bytes = $stats['bytes'];
        $human = $bytes >= 1_048_576
            ? number_format($bytes / 1_048_576, 1).' MB'
            : number_format($bytes / 1024, 1).' KB';

        return [
            Stat::make(__('vmedia::admin.stats.media'), (string) $stats['media'])
                ->description(__('vmedia::admin.stats.media_help', [
                    'images' => $stats['images'],
                    'videos' => $stats['videos'],
                    'files' => $stats['files'],
                ])),
            Stat::make(__('vmedia::admin.stats.galleries'), (string) $stats['galleries']),
            Stat::make(__('vmedia::admin.stats.storage'), $human),
            Stat::make(__('vmedia::admin.stats.orphans'), (string) $stats['orphans'])
                ->description(__('vmedia::admin.stats.trashed', ['count' => $stats['trashed']])),
        ];
    }
}
