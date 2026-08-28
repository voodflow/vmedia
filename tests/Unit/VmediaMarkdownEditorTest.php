<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Voodflow\Vmedia\Filament\Forms\Components\VmediaMarkdownEditor;
use Voodflow\Vmedia\Tests\OrchestraTestCase;

class VmediaMarkdownEditorTest extends OrchestraTestCase
{
    public function test_embedded_html_delegates_to_filament_markdown_editor_and_adds_vmedia_hook(): void
    {
        $field = Livewire::test(VmediaMarkdownEditorHarness::class)
            ->call('mountForm')
            ->instance()
            ->form
            ->getComponent('content');

        $this->assertInstanceOf(VmediaMarkdownEditor::class, $field);

        $html = $field->toEmbeddedHtml();

        $this->assertStringContainsString('markdownEditorFormComponent({', $html);
        $this->assertStringContainsString('setUpUsing: window.vmediaMarkdownEditorSetUp', $html);
        $this->assertStringContainsString('data-vmedia-component-key', $html);
        $this->assertStringContainsString('vmedia-markdown-insert', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
    }

    public function test_insert_action_is_registered(): void
    {
        $field = VmediaMarkdownEditor::make('content');

        $this->assertTrue($field->hasAction('insertVmediaImage'));
    }
}

class VmediaMarkdownEditorHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [
        'content' => "## Hello\n\nWorld",
    ];

    public function mountForm(): static
    {
        $this->form->fill($this->data);

        return $this;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                VmediaMarkdownEditor::make('content'),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
