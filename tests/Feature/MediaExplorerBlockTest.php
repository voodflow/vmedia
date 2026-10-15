<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Voodflow\Vmedia\Livewire\MediaExplorer as MediaExplorerComponent;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaExplorer;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Tests\TestCase;
use Voodflow\Vmedia\Voodbuilder\MediaExplorerBlock;
use Voodflow\Voodbuilder\Contracts\EditorConfigurableBlock;
use Voodflow\Voodbuilder\Contracts\EditorLivewireBlock;

/**
 * The VoodMedia block exposed to VoodBuilder.
 *
 * These assertions only run where `voodflow/voodbuilder` is installed: the block
 * implements its contracts, so the class cannot even be loaded without it. The
 * no-VoodBuilder guarantee is covered by {@see MediaExplorerWithoutVoodbuilderTest}.
 */
final class MediaExplorerBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! interface_exists(EditorLivewireBlock::class)) {
            $this->markTestSkipped('voodflow/voodbuilder is not installed in this suite.');
        }

        // The fake image bytes are identical across calls, so the duplicate detector
        // would collapse the fixtures into one vault row and hide the filter behaviour
        // these tests are about.
        config()->set('vmedia.duplicates.detect', false);
        config()->set('vmedia.duplicates.reuse', false);
    }

    public function test_the_block_implements_the_editor_and_livewire_contracts(): void
    {
        $this->assertTrue(is_subclass_of(MediaExplorerBlock::class, EditorConfigurableBlock::class));
        $this->assertTrue(is_subclass_of(MediaExplorerBlock::class, EditorLivewireBlock::class));
        $this->assertSame('vmedia_media_explorer', MediaExplorerBlock::getId());
        $this->assertSame('vmedia-media-explorer', MediaExplorerBlock::component());
        $this->assertNotSame('', MediaExplorerBlock::getLabel());
    }

    public function test_the_livewire_component_is_registered_under_the_block_component_name(): void
    {
        $this->assertSame(
            MediaExplorerComponent::class,
            app('livewire.finder')->resolveClassComponentClassName(MediaExplorerBlock::component()),
        );
    }

    public function test_normalize_config_clamps_per_page_and_defaults_the_heading(): void
    {
        $config = MediaExplorerBlock::normalizeConfig([
            'per_page' => 999,
            'show_search' => 'no',
        ]);

        $this->assertSame(MediaExplorer::MAX_PER_PAGE, $config['per_page']);
        $this->assertNotSame('', $config['heading']);
        $this->assertFalse($config['show_search']);
        $this->assertTrue($config['show_type_filter']);
        $this->assertTrue($config['show_album_filter']);

        $empty = MediaExplorerBlock::normalizeConfig(['heading' => '   ']);

        $this->assertNotSame('', $empty['heading']);
        $this->assertSame(MediaExplorer::DEFAULT_PER_PAGE, $empty['per_page']);
    }

    public function test_props_from_config_is_an_allowlist(): void
    {
        $props = MediaExplorerBlock::propsFromConfig([
            'heading' => 'Archive',
            'per_page' => 3,
            'secret' => 'never-forwarded',
        ]);

        $this->assertSame(
            ['heading', 'perPage', 'showSearch', 'showTypeFilter', 'showAlbumFilter'],
            array_keys($props),
        );
        $this->assertSame(3, $props['perPage']);
        $this->assertArrayNotHasKey('secret', $props);
    }

    public function test_the_canvas_preview_is_a_static_snapshot_with_inert_controls(): void
    {
        $album = $this->album();
        $this->image('photo-1', $album);
        $this->image('photo-2', $album);
        $this->image('photo-3', $album);

        $html = MediaExplorerBlock::toPreviewHtml(['heading' => 'Archive', 'per_page' => 2], []);

        // Declarative config markers: a dynamic block routes these into data-voodbuilder-config.
        foreach (['heading', 'per_page', 'show_search', 'show_type_filter', 'show_album_filter'] as $field) {
            $this->assertStringContainsString('data-vb-field="' . $field . '"', $html);
        }
        $this->assertStringContainsString('data-vb-field-type="number"', $html);
        $this->assertStringContainsString('data-vb-status="yes"', $html);

        // The per_page marker seeds the *configured* value, not how many tiles fit.
        $this->assertMatchesRegularExpression('/data-vb-field="per_page"[^>]*>\s*2\s*</s', $html);

        // Static canvas: same structure and classes, no Livewire, inert controls.
        $this->assertStringNotContainsString('wire:', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('data-voodbuilder-skip-cta="true"', $html);

        // Real data: exactly one page of tiles, and a load-more stand-in because 3 > 2.
        $this->assertSame(2, substr_count($html, 'vmedia-media-explorer__item'));
        $this->assertStringContainsString('data-vb-status="yes"', $html);
    }

    public function test_the_published_render_mounts_livewire_and_drops_editor_markers(): void
    {
        $album = $this->album();
        $this->image('photo-1', $album);
        $this->image('photo-2', $album);
        $this->image('photo-3', $album);

        $html = MediaExplorerBlock::toHtml(['heading' => 'Archive', 'per_page' => 2], []);

        $this->assertStringContainsString('wire:snapshot', $html);
        $this->assertStringContainsString('wire:id', $html);
        $this->assertStringContainsString('wire:model.live', $html);
        $this->assertStringContainsString('wire:click="loadMore"', $html);
        $this->assertStringContainsString('wire:click="setType(', $html);

        // The mount carries none of the editor plumbing.
        $this->assertStringNotContainsString('data-vb-field', $html);
        $this->assertStringNotContainsString('aria-disabled', $html);
    }

    public function test_the_livewire_component_filters_searches_and_loads_more(): void
    {
        $album = $this->album();
        $alpha = $this->image('arduino-board', $album);
        $this->image('sunrise', $album);
        $this->image('potentiometer', $album);

        Livewire::test(MediaExplorerComponent::class)
            ->assertSee('arduino-board')
            ->set('search', 'arduino')
            ->assertSee('arduino-board')
            ->assertDontSee('sunrise')
            ->set('type', 'video')
            ->assertDontSee('arduino-board')
            ->set('type', 'image')
            ->set('search', '')
            ->assertSee('sunrise')
            ->call('loadMore')
            ->assertSet('limit', 24);
    }

    private function album(): MediaGallery
    {
        return MediaGallery::query()->create([
            'name' => 'Library',
            'kind' => MediaGallery::KIND_ALBUM,
            'is_default' => false,
            'is_public' => true,
            'sort_order' => 1,
            'parent_key' => 0,
        ]);
    }

    private function image(string $name, MediaGallery $album): MediaItem
    {
        Storage::fake('public');

        return MediaLibrary::store(
            UploadedFile::fake()->image($name . '.jpg', 16, 16),
            $album,
            $name,
        );
    }
}
