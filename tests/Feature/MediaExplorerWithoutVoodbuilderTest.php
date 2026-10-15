<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Livewire\Livewire;
use Voodflow\Vmedia\Livewire\MediaExplorer;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\MediaExplorer as MediaExplorerQuery;
use Voodflow\Vmedia\Support\VmediaEditorBlocks;
use Voodflow\Vmedia\Support\VmediaEditorBridge;
use Voodflow\Vmedia\Tests\TestCase;
use Voodflow\Vmedia\Voodbuilder\MediaExplorerBlock;
use Voodflow\Voodbuilder\Voodbuilder;

/**
 * VoodMedia is required by apps that have no page builder, so the editor block is a
 * soft dependency: nothing VoodBuilder-related may be referenced unless the package
 * is installed.
 *
 * The guarantee can only be asserted for real where `voodflow/voodbuilder` is genuinely
 * absent, i.e. the isolated package suite (`php vendor/bin/phpunit` from the package).
 * Where VoodBuilder *is* installed the class skips; {@see MediaExplorerBlockTest} covers
 * the other side of the boundary.
 */
final class MediaExplorerWithoutVoodbuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Voodbuilder::class)) {
            $this->markTestSkipped('VoodBuilder is installed; the no-VoodBuilder guarantee runs in the isolated package suite.');
        }
    }

    public function test_booting_and_registering_never_loads_a_voodbuilder_block(): void
    {
        $this->assertFalse(class_exists(Voodbuilder::class));

        // The provider already booted above: the guard must have run before the block
        // class was referenced, so the class that implements the VoodBuilder contract
        // is still unloaded.
        $this->assertFalse(class_exists(MediaExplorerBlock::class, false));

        // Registering again is a no-op, not a fatal (a missing interface cannot leak
        // out of the guarded path).
        VmediaEditorBlocks::register();
        VmediaEditorBridge::register();

        $this->assertFalse(class_exists(MediaExplorerBlock::class, false));
    }

    public function test_the_livewire_component_is_registered_but_the_editor_block_is_not(): void
    {
        // The component is a plain VoodMedia feature: usable from any Blade page even
        // when no page builder is installed.
        $this->assertSame(
            MediaExplorer::class,
            app('livewire.finder')->resolveClassComponentClassName(MediaExplorer::NAME),
        );

        // The block, however, needs the VoodBuilder contracts and stays unloaded.
        $this->assertFalse(class_exists(MediaExplorerBlock::class, false));
    }

    public function test_voodmedia_itself_still_works(): void
    {
        $this->assertTrue(class_exists(MediaGallery::class));
        $this->assertTrue(MediaGallery::default()->exists);
    }

    public function test_the_explorer_component_mounts_without_a_page_builder(): void
    {
        // The whole point of keeping the component separate from the block: an app
        // with no VoodBuilder can still drop it into a Blade page.
        Livewire::test(MediaExplorer::NAME)
            ->assertOk()
            ->assertSet('perPage', MediaExplorerQuery::DEFAULT_PER_PAGE)
            ->assertSee(__('vmedia::admin.editor.explorer_empty'));
    }
}
