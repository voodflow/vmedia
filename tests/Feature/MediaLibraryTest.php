<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    public function test_store_attaches_to_default_gallery_and_builds_safe_url(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $media = MediaLibrary::store(
            UploadedFile::fake()->image('summer.png', 32, 32),
            MediaGallery::default(),
            'Summer',
        );

        $payload = MediaLibrary::toAssetPayload($media);

        $this->assertSame('Summer', $payload['name']);
        $this->assertSame('image', $payload['type']);
        $this->assertStringStartsWith('/storage/', $payload['src']);
        $this->assertStringNotContainsString('..', $payload['src']);
        $this->assertContains((int) MediaGallery::default()->getKey(), $payload['gallery_ids']);
    }

    public function test_assign_galleries_can_replace_memberships(): void
    {
        Storage::fake('public');
        $default = MediaGallery::default();
        $other = MediaGallery::query()->create([
            'name' => 'Events',
            'is_default' => false,
            'is_public' => true,
            'sort_order' => 1,
        ]);

        $media = MediaLibrary::store(
            UploadedFile::fake()->image('a.jpg', 8, 8),
            $default,
        );

        MediaLibrary::assignGalleries([$media], [(int) $other->getKey()], replace: true);
        $media->refresh()->load('galleries');

        $this->assertSame([(int) $other->getKey()], $media->galleries->pluck('id')->map(fn ($id) => (int) $id)->all());
    }
}
