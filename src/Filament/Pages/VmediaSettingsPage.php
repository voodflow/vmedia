<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use Voodflow\Vmedia\Models\VmediaSettings;
use Voodflow\Vmedia\Support\ConversionLadder;

/**
 * @property-read Schema $form
 */
class VmediaSettingsPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'vmedia/settings';

    protected static ?string $title = 'Settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return (string) config('vmedia.navigation.group', 'VoodMedia');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('vmedia.navigation.sort', 40) + 10;
    }

    public static function getNavigationLabel(): string
    {
        return __('vmedia::admin.settings.navigation');
    }

    public function getTitle(): string | Htmlable
    {
        return __('vmedia::admin.settings.title');
    }

    public function mount(): void
    {
        $this->data = VmediaSettings::data();
        $this->form->fill($this->data);
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();
            VmediaSettings::saveData($data);

            $this->commitDatabaseTransaction();

            Notification::make()
                ->success()
                ->title(__('vmedia::admin.settings.saved'))
                ->body(__('vmedia::admin.settings.saved_body'))
                ->send();
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $this->rollBackDatabaseTransaction()
                : $this->commitDatabaseTransaction();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }
    }

    public function regenerateConversions(): void
    {
        $exit = Artisan::call('vmedia:regenerate-conversions', [
            '--force' => true,
        ]);

        if ($exit !== 0) {
            Notification::make()
                ->danger()
                ->title(__('vmedia::admin.settings.regenerate_failed'))
                ->body(Artisan::output())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('vmedia::admin.settings.regenerate_done'))
            ->body(Artisan::output())
            ->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('vmedia::admin.settings.variants_heading'))
                    ->description(__('vmedia::admin.settings.variants_help'))
                    ->schema([
                        Repeater::make('variants')
                            ->label(__('vmedia::admin.settings.variants'))
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('vmedia::admin.settings.variant_key'))
                                    ->required()
                                    ->maxLength(32)
                                    ->helperText(__('vmedia::admin.settings.variant_key_help'))
                                    ->regex('/^[a-z][a-z0-9_-]{0,31}$/'),
                                TextInput::make('label')
                                    ->label(__('vmedia::admin.settings.variant_label'))
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('width')
                                    ->label(__('vmedia::admin.settings.variant_width'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(10000),
                                TextInput::make('height')
                                    ->label(__('vmedia::admin.settings.variant_height'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(10000)
                                    ->helperText(__('vmedia::admin.settings.variant_height_help'))
                                    ->default(0),
                                Select::make('format')
                                    ->label(__('vmedia::admin.settings.variant_format'))
                                    ->options([
                                        'webp' => 'WebP',
                                        'jpg' => 'JPEG',
                                        'png' => 'PNG',
                                        'avif' => 'AVIF',
                                    ])
                                    ->required()
                                    ->default('webp'),
                                Select::make('role')
                                    ->label(__('vmedia::admin.settings.variant_role'))
                                    ->options([
                                        ConversionLadder::ROLE_THUMB => __('vmedia::admin.settings.role_thumb'),
                                        ConversionLadder::ROLE_DISPLAY => __('vmedia::admin.settings.role_display'),
                                    ])
                                    ->required()
                                    ->default(ConversionLadder::ROLE_DISPLAY),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                                ? (string) $state['label']
                                : (string) ($state['key'] ?? ''))
                            ->addActionLabel(__('vmedia::admin.settings.add_variant'))
                            ->minItems(1)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                        Select::make('default_display')
                            ->label(__('vmedia::admin.settings.default_display'))
                            ->helperText(__('vmedia::admin.settings.default_display_help'))
                            ->options(function (Get $get): array {
                                $options = [];

                                foreach ((array) ($get('variants') ?? []) as $row) {
                                    if (! is_array($row)) {
                                        continue;
                                    }

                                    $key = ConversionLadder::sanitizeKey((string) ($row['key'] ?? ''));

                                    if ($key === '') {
                                        continue;
                                    }

                                    $label = trim((string) ($row['label'] ?? $key));
                                    $options[$key] = $label . ' (' . $key . ')';
                                }

                                return $options;
                            })
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    /**
     * @return array<Action|Actions>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('vmedia::admin.settings.save'))
                ->submit('save'),
            Action::make('regenerate')
                ->label(__('vmedia::admin.settings.regenerate'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('vmedia::admin.settings.regenerate_confirm_title'))
                ->modalDescription(__('vmedia::admin.settings.regenerate_confirm_body'))
                ->action('regenerateConversions'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->alignment(Alignment::Start)
                        ->key('form-actions'),
                ]),
        ]);
    }
}
