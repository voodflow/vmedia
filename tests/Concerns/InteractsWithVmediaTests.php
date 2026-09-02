<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Concerns;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Tests\User;
use Voodflow\Vmedia\Vmedia;

trait InteractsWithVmediaTests
{
    protected function bootVmediaTests(): void
    {
        $this->configureVmedia();
        $this->grantVmediaAbilities();

        Vmedia::reset();
        Vmedia::activate();
    }

    /**
     * Let the signed-in test user manage media.
     *
     * The policies ask for Shield-style abilities (`Create:MediaItem`), which nothing in a
     * package test suite grants — so every authenticated request answered 403 and the
     * upload, gallery and delete tests asserted nothing about the behaviour they name.
     *
     * Scoped to this package's models on purpose: abilities like `manage-vmedia`, which
     * `vmedia.authorization.ability` points at, must stay deniable, and a blanket allow
     * would quietly turn the middleware test green too.
     */
    protected function grantVmediaAbilities(): void
    {
        Gate::before(static function ($user, string $ability): ?bool {
            return preg_match('/^[A-Za-z]+:(MediaItem|MediaGallery|MediaTag)$/', $ability) === 1
                ? true
                : null;
        });
    }

    protected function configureVmedia(): void
    {
        config()->set('vmedia.enabled', true);
        config()->set('vmedia.auto_register', true);
        config()->set('vmedia.integrations.voodbuilder.editor_routes', true);
        config()->set('vmedia.authorization.ability', null);
        config()->set('vmedia.disk', 'public');
        config()->set('vmedia.conversions.enabled', false);
        config()->set('vmedia.duplicates.detect', true);
        config()->set('vmedia.duplicates.reuse', true);
        config()->set('vmedia.usage.protect_delete', false);
        config()->set('vmedia.soft_deletes', true);
        config()->set('vmedia.public.enabled', true);
        config()->set('media-library.media_model', MediaItem::class);
        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/public'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
        ]);
        config()->set('media-library.disk_name', 'public');

        $publicRoot = storage_path('framework/testing/disks/public');

        if (! is_dir($publicRoot)) {
            mkdir($publicRoot, 0777, true);
        }
    }

    protected function actingAsUser(): User
    {
        $user = User::query()->create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function assertVmediaRouteRegistered(string $name): void
    {
        $matched = false;

        foreach (Route::getRoutes() as $route) {
            if ($route->getName() === $name) {
                $matched = true;
                break;
            }
        }

        $this->assertTrue($matched, "Expected route [{$name}] to be registered.");
    }
}
