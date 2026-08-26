<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Html;
use Illuminate\Support\HtmlString;

/**
 * Soft horizontal rule between stacked VmediaPicker fields (logo / gallery / files).
 */
final class MediaFieldsDivider
{
    public static function make(): Html
    {
        return Html::make(new HtmlString(
            '<hr class="my-1 w-full border-0 border-t border-gray-200 dark:border-white/10" />'
        ))->columnSpanFull();
    }

    /**
     * Interleave dividers between schema components (skips empty lists).
     *
     * @param  list<Component>  $components
     * @return list<Component>
     */
    public static function between(array $components): array
    {
        $components = array_values(array_filter($components));

        if (count($components) < 2) {
            return $components;
        }

        $out = [];

        foreach ($components as $index => $component) {
            if ($index > 0) {
                $out[] = self::make();
            }

            $out[] = $component;
        }

        return $out;
    }
}
