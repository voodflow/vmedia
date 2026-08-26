<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\UploadGuard;
use Voodflow\Vmedia\Tests\TestCase;

class MediaSecurityTest extends TestCase
{
    public function test_guest_cannot_list_galleries_or_media(): void
    {
        $this->getJson(route('vmedia.media.galleries'))->assertUnauthorized();
        $this->getJson(route('vmedia.media.index'))->assertUnauthorized();
        $this->postJson(route('vmedia.media.upload'), [])->assertUnauthorized();
    }

    public function test_guest_cannot_use_compat_editor_aliases(): void
    {
        $this->assertVmediaRouteRegistered('voodbuilder.editor.media.galleries');
        $this->getJson(route('voodbuilder.editor.media.galleries'))->assertUnauthorized();
        $this->getJson(route('voodbuilder.editor.media.index'))->assertUnauthorized();
        $this->postJson(route('voodbuilder.editor.upload'), [])->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_galleries(): void
    {
        $this->actingAsUser();
        MediaGallery::default();

        $this->getJson(route('vmedia.media.galleries'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'default_gallery_id',
                'upload_gallery_id',
            ]);
    }

    public function test_optional_ability_denies_authenticated_user(): void
    {
        config()->set('vmedia.authorization.ability', 'manage-vmedia');
        Gate::define('manage-vmedia', fn (): bool => false);

        $this->actingAsUser();

        $this->getJson(route('vmedia.media.galleries'))->assertForbidden();
    }

    public function test_upload_sanitizes_path_traversal_in_display_name(): void
    {
        $this->actingAsUser();
        Storage::fake('public');
        MediaGallery::default();

        $response = $this->postJson(route('vmedia.media.upload'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 20, 20),
            'name' => '../../etc/passwd',
        ]);

        $response->assertCreated()
            ->assertJsonPath('media.name', 'image');
    }

    public function test_upload_guard_rejects_null_byte_in_client_name(): void
    {
        $safe = UploadedFile::fake()->image('ok.jpg', 8, 8);
        $evil = new UploadedFile(
            $safe->getRealPath(),
            "ok\0.jpg",
            'image/jpeg',
            null,
            true,
        );

        $this->expectException(ValidationException::class);
        UploadGuard::assertSafeUpload($evil);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $this->actingAsUser();

        $file = UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload');

        $this->postJson(route('vmedia.media.upload'), [
            'file' => $file,
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_upload_image_to_default_gallery(): void
    {
        $this->actingAsUser();
        Storage::fake('public');
        MediaGallery::default();

        $file = UploadedFile::fake()->image('photo.jpg', 20, 20);

        $response = $this->postJson(route('vmedia.media.upload'), [
            'file' => $file,
            'name' => 'Hero shot',
        ]);

        $response->assertCreated()
            ->assertJsonPath('media.name', 'Hero shot')
            ->assertJsonPath('media.type', 'image');

        $this->assertDatabaseCount('media', 1);
        $media = MediaItem::query()->first();
        $this->assertNotNull($media);
        $this->assertTrue(MediaLibrary::isVaultMedia($media));
        $this->assertTrue($media->galleries()->where('is_default', true)->exists());
    }

    public function test_delete_requires_authorization_and_vault_ownership(): void
    {
        $this->actingAsUser();
        Storage::fake('public');

        $media = MediaLibrary::store(
            UploadedFile::fake()->image('keep.jpg', 10, 10),
            MediaGallery::default(),
        );

        $this->deleteJson(route('vmedia.media.destroy', $media))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertSoftDeleted('media', ['id' => $media->getKey()]);
    }

    public function test_guest_cannot_delete_media(): void
    {
        Storage::fake('public');
        $this->actingAsUser();
        $media = MediaLibrary::store(
            UploadedFile::fake()->image('x.jpg', 10, 10),
            MediaGallery::default(),
        );
        auth()->logout();

        $this->deleteJson(route('vmedia.media.destroy', $media))->assertUnauthorized();
        $this->assertDatabaseHas('media', ['id' => $media->getKey()]);
    }

    public function test_cannot_delete_non_vault_media_row(): void
    {
        $this->actingAsUser();

        $foreign = MediaItem::query()->create([
            'model_type' => 'unrelated_model',
            'model_id' => 1,
            'uuid' => (string) str()->uuid(),
            'collection_name' => 'images',
            'name' => 'foreign',
            'file_name' => 'foreign.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $this->deleteJson(route('vmedia.media.destroy', $foreign))->assertForbidden();
        $this->assertDatabaseHas('media', ['id' => $foreign->getKey()]);
    }

    public function test_default_gallery_cannot_be_deleted_by_policy(): void
    {
        $this->actingAsUser();
        $gallery = MediaGallery::default();

        $this->assertFalse(Gate::forUser(auth()->user())->allows('delete', $gallery));
    }

    public function test_public_url_rejects_path_traversal_relative_paths(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $media = new MediaItem;
        $media->id = 1;
        $media->file_name = 'x.jpg';
        $media->disk = 'public';

        $media = \Mockery::mock($media)->makePartial();
        $media->shouldReceive('getPathRelativeToRoot')->andReturn('../secrets/x.jpg');

        MediaLibrary::publicUrl($media);
    }

    public function test_package_routes_register_without_page_builder(): void
    {
        $this->assertVmediaRouteRegistered('vmedia.media.galleries');
        $this->assertVmediaRouteRegistered('vmedia.media.index');
        $this->assertVmediaRouteRegistered('vmedia.media.upload');
        $this->assertVmediaRouteRegistered('vmedia.media.destroy');
        $this->assertVmediaRouteRegistered('voodbuilder.editor.media.galleries');
        $this->assertVmediaRouteRegistered('voodbuilder.editor.upload');
    }
}
