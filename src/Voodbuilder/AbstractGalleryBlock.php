<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Voodbuilder;

use Voodflow\Vmedia\Support\GalleryBlockSupport;

/**
 * Shared VoodBuilder server block for VoodMedia gallery presentations.
 *
 * Soft dependency: does not implement VoodBuilder contracts so the class can
 * load without `voodflow/voodbuilder`. Signatures match EditorServerBlock /
 * EditorConfigurableBlock.
 */
abstract class AbstractGalleryBlock
{
    abstract public static function getId(): string;

    abstract public static function getLabel(): string;

    abstract protected static function layout(): string;

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return GalleryBlockSupport::defaultConfig(static::layout());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalizeConfig(array $config): array
    {
        return GalleryBlockSupport::normalizeConfig($config, static::layout());
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public static function toHtml(array $config, array $context): string
    {
        return static::render($config, preview: false);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public static function toPreviewHtml(array $config, array $context): string
    {
        return static::render($config, preview: true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function render(array $config, bool $preview): string
    {
        $config = static::normalizeConfig($config);
        $slides = GalleryBlockSupport::resolveSlides($config, $preview);

        return view(GalleryBlockSupport::viewFor(static::layout()), [
            'config' => $config,
            'ui' => GalleryBlockSupport::uiClasses($config),
            'slides' => $slides,
            'preview' => $preview,
            'blockId' => static::getId(),
            'empty' => $slides === [],
        ])->render();
    }
}
