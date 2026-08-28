<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\RichEditor;

/**
 * Rich editor with an extra toolbar button to insert images from the vmedia library.
 */
class VmediaRichEditor extends RichEditor
{
    protected string|Closure $vaultPlugin = 'voodbuilder';

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugins(fn (VmediaRichEditor $component): array => [
            VmediaRichContentPlugin::make($component->getVaultPlugin()),
        ]);

        $this->enableToolbarButtons(['vmediaAttach']);
    }

    public function vaultPlugin(string|Closure $plugin): static
    {
        $this->vaultPlugin = $plugin;

        return $this;
    }

    public function getVaultPlugin(): string
    {
        return (string) $this->evaluate($this->vaultPlugin);
    }
}
