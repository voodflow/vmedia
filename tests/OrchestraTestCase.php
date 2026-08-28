<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Voodflow\Vmedia\Tests\Concerns\InteractsWithVmediaTests;
use Voodflow\Vmedia\VmediaServiceProvider;

/**
 * Package-isolated Testbench base (run from packages/voodflow/vmedia).
 */
abstract class OrchestraTestCase extends BaseTestCase
{
    use InteractsWithVmediaTests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootVmediaTests();
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
            $table->softDeletes();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
