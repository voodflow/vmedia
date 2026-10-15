<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Voodbuilder;

use Voodflow\Vmedia\Livewire\MediaExplorer as MediaExplorerComponent;
use Voodflow\Vmedia\Support\MediaExplorer;
use Voodflow\Voodbuilder\Contracts\EditorConfigurableBlock;
use Voodflow\Voodbuilder\Contracts\EditorLivewireBlock;
use Voodflow\Voodbuilder\Support\Editor\EditorLivewireBlockRenderer;

/**
 * VoodBuilder server block: a public, browsable **media explorer**.
 *
 * Published output is the `vmedia-media-explorer` Livewire component (server-side
 * search, type / album filters, load-more, real count). The canvas renders the same
 * Blade partial as a static preview with inert controls — it never mounts Livewire.
 *
 * Hard dependency rule for VoodMedia: this class implements VoodBuilder contracts,
 * so it must only ever be autoloaded when `voodflow/voodbuilder` is installed.
 * Registration is guarded in `Voodflow\Vmedia\Support\VmediaEditorBlocks::register()`
 * before any reference to this class.
 */
final class MediaExplorerBlock implements EditorConfigurableBlock, EditorLivewireBlock
{
    public static function getId(): string
    {
        return 'vmedia_media_explorer';
    }

    public static function getLabel(): string
    {
        return (string) __('vmedia::admin.editor.blocks.explorer');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return self::normalizeConfig([]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalizeConfig(array $config): array
    {
        $heading = trim((string) ($config['heading'] ?? ''));

        if ($heading === '') {
            $heading = (string) __('vmedia::admin.editor.explorer_default_heading');
        }

        return [
            'heading' => $heading,
            'per_page' => MediaExplorer::normalizePerPage($config['per_page'] ?? MediaExplorer::DEFAULT_PER_PAGE),
            'show_search' => self::toBool($config['show_search'] ?? true),
            'show_type_filter' => self::toBool($config['show_type_filter'] ?? true),
            'show_album_filter' => self::toBool($config['show_album_filter'] ?? true),
        ];
    }

    public static function component(): string
    {
        return MediaExplorerComponent::NAME;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function propsFromConfig(array $config): array
    {
        $config = self::normalizeConfig($config);

        return [
            'heading' => $config['heading'],
            'perPage' => $config['per_page'],
            'showSearch' => $config['show_search'],
            'showTypeFilter' => $config['show_type_filter'],
            'showAlbumFilter' => $config['show_album_filter'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public static function toHtml(array $config, array $context): string
    {
        return EditorLivewireBlockRenderer::mount(self::class, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public static function toPreviewHtml(array $config, array $context): string
    {
        $config = self::normalizeConfig($config !== [] ? $config : self::defaultConfig());

        // The preview mirrors the *initial* state of the published component: the
        // configured page size, the same filters, and the same real count / load-more
        // gate. Both run the same query through the same support class, so the canvas
        // footer is a snapshot rather than an invented number.
        $result = MediaExplorer::paginate(null, null, null, 1, $config['per_page']);

        return view('vmedia::blocks.media-explorer', [
            'heading' => $config['heading'],
            'live' => false,
            'authoring' => true,
            'showSearch' => $config['show_search'],
            'showTypeFilter' => $config['show_type_filter'],
            'showAlbumFilter' => $config['show_album_filter'],
            'search' => '',
            'type' => '',
            'albumId' => null,
            'perPage' => $config['per_page'],
            'assets' => $result['data'],
            'total' => $result['meta']['total'],
            'hasMore' => $result['meta']['has_more'],
            'typeCounts' => MediaExplorer::typeCounts(),
            'albums' => MediaExplorer::albumOptions(),
        ])->render();
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
