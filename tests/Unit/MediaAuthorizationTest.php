<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Foundation\Auth\User;
use Voodflow\Vmedia\Support\MediaAuthorization;
use Voodflow\Vmedia\Tests\TestCase;

class MediaAuthorizationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            FilamentServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(PanelRegistry::class)->register(
            Panel::make()
                ->id('admin')
                ->path('admin')
                ->default(),
        );
    }

    public function test_plain_user_without_shield_can_manage_media(): void
    {
        config(['vmedia.authorization.driver' => 'auto']);

        $user = new User;
        $user->forceFill([
            'name' => 'Admin',
            'email' => 'media-admin@example.com',
            'password' => bcrypt('secret'),
        ])->save();

        $this->actingAs($user);

        $this->assertNull(Filament::getCurrentPanel());
        $this->assertTrue(MediaAuthorization::allows($user, 'Create:MediaItem'));
        $this->assertTrue(MediaAuthorization::allows($user, 'ViewAny:MediaGallery'));
    }

    public function test_panel_driver_allows_plain_authenticated_user(): void
    {
        config(['vmedia.authorization.driver' => 'panel']);

        $user = new User;
        $user->forceFill([
            'name' => 'Editor',
            'email' => 'editor-media@example.com',
            'password' => bcrypt('secret'),
        ])->save();

        $this->actingAs($user);

        $this->assertTrue(MediaAuthorization::allows($user, 'Create:MediaItem'));
    }
}
