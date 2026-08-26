<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Vmedia;
use Voodflow\Vmedia\VmediaServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('vmedia.enabled', true);
        config()->set('vmedia.auto_register', true);
        config()->set('vmedia.integrations.voodbuilder.editor_routes', true);
        config()->set('vmedia.authorization.ability', null);
        config()->set('vmedia.disk', 'public');
        config()->set('vmedia.conversions.enabled', false);
        config()->set('vmedia.duplicates.detect', true);
        config()->set('vmedia.duplicates.reuse', true);
        config()->set('vmedia.usage.protect_delete', true);
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

        Vmedia::reset();
        Vmedia::activate();
    }

    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            VmediaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $schema->create('media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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
