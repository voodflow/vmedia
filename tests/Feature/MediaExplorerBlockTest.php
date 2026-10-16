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

    public function test_the_lightbox_opens_and_closes_on_livewire_state(): void
    {
        $album = $this->album();
        $first = $this->image('photo-1', $album);
        $this->image('photo-2', $album);

        Livewire::test(MediaExplorerComponent::class)
            ->assertSet('activeMediaId', null)
            ->call('openMedia', (int) $first->getKey())
            ->assertSet('activeMediaId', (int) $first->getKey())
            // The live mount wires the tile to the component, and the dialog is modal.
            ->assertSee('wire:click="openMedia(' . $first->getKey() . ')"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->call('closeMedia')
            ->assertSet('activeMediaId', null)
            ->assertDontSee('role="dialog"', false);
    }

    public function test_the_lightbox_never_opens_a_media_outside_the_visible_window(): void
    {
        $public = $this->album();
        $private = MediaGallery::query()->create([
            'name' => 'Private album',
            'kind' => MediaGallery::KIND_ALBUM,
            'is_default' => false,
            'is_public' => false,
            'sort_order' => 2,
            'parent_key' => 0,
        ]);

        $hidden = $this->image('hidden', $private);
        $this->image('arduino-board', $public);
        $this->image('sunrise', $public);

        $component = Livewire::test(MediaExplorerComponent::class);

        // A private-album media is not in the visible window at all.
        $component->call('openMedia', (int) $hidden->getKey())
            ->assertSet('activeMediaId', null);

        // Nor is a media the active search filtered out.
        $visible = MediaItem::query()->where('name', 'arduino-board')->firstOrFail();

        $component->set('search', 'sunrise')
            ->call('openMedia', (int) $visible->getKey())
            ->assertSet('activeMediaId', null);
    }

    public function test_the_lightbox_navigates_within_the_filtered_window_only(): void
    {
        $album = $this->album();
        $this->image('arduino-board', $album);
        $this->image('arduino-patch', $album);
        $this->image('sunrise', $album);

        $component = Livewire::test(MediaExplorerComponent::class)
            ->set('search', 'arduino'); // window = the two "arduino" media

        $ids = MediaItem::query()
            ->whereIn('name', ['arduino-board', 'arduino-patch'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $component->call('openMedia', max($ids))
            ->assertSet('activeMediaId', max($ids))
            // Clamped at the top of the window: never steps into the filtered-out "sunrise".
            ->call('previousMedia')
            ->assertSet('activeMediaId', max($ids))
            ->call('nextMedia')
            ->assertSet('activeMediaId', min($ids))
            // Clamped at the bottom too.
            ->call('nextMedia')
            ->assertSet('activeMediaId', min($ids))
            ->assertSee('wire:click="previousMedia"', false)
            ->assertSee('wire:click="nextMedia"', false);

        // Changing the filters closes the lightbox instead of leaving it on a stale id.
        $component->call('closeMedia')->set('search', '')->call('openMedia', max($ids))
            ->assertSet('activeMediaId', max($ids))
            ->set('search', 'sunrise')
            ->assertSet('activeMediaId', null);
    }

    public function test_the_lightbox_shows_a_credit_line_only_when_the_media_has_one(): void
    {
        $album = $this->album();
        $credited = $this->image('pixabay-photo', $album);
        $credited->setCaption('Photo by Jane Doe on Pixabay')->save();
        $this->image('bare-photo', $album);

        $component = Livewire::test(MediaExplorerComponent::class)
            ->call('openMedia', (int) $credited->getKey())
            ->assertSee('Photo by Jane Doe on Pixabay');

        $bare = MediaItem::query()->where('name', 'bare-photo')->firstOrFail();

        $component->call('openMedia', (int) $bare->getKey())
            ->assertDontSee('Photo by Jane Doe on Pixabay');
    }

    public function test_the_lightbox_prefers_the_dedicated_credits_field_over_the_caption(): void
    {
        $album = $this->album();
        $media = $this->image('credited-photo', $album);
        $media->setCaption('Caption text')->setCredits('Photo by Jane Doe on Pixabay')->save();

        Livewire::test(MediaExplorerComponent::class)
            ->call('openMedia', (int) $media->getKey())
            ->assertSee('Photo by Jane Doe on Pixabay')
            ->assertDontSee('Caption text');
    }

    public function test_the_canvas_preview_has_an_inert_lightbox_without_livewire(): void
    {
        $album = $this->album();
        $this->image('photo-1', $album);

        $html = MediaExplorerBlock::toPreviewHtml(['heading' => 'Archive'], []);

        // The lightbox markup is shipped (so its utilities reach the compiled page CSS)
        // inside a `hidden` wrapper, and none of its controls are live.
        $this->assertStringContainsString('data-vmedia-explorer-lightbox', $html);
        $this->assertStringContainsString('data-vmedia-explorer-lightbox-root', $html);
        $this->assertStringContainsString('class="hidden"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringNotContainsString('wire:', $html);
        $this->assertStringNotContainsString('openMedia(', $html);
        $this->assertStringContainsString('data-voodbuilder-skip-cta="true"', $html);
        $this->assertStringContainsString('max-h-[90vh]', $html);
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
