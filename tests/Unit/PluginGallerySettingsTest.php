<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Support\Integration\PluginGallerySettings;
use Voodflow\Vmedia\Support\Integration\PluginGallerySettingsDefaults;
use Voodflow\Vmedia\Tests\TestCase;

class PluginGallerySettingsTest extends TestCase
{
    public function test_vexhibitors_defaults_use_short_album_identity(): void
    {
        $settings = PluginGallerySettings::fromArray(PluginGallerySettingsDefaults::vexhibitors());

        $this->assertSame('{exhibitor.company_name}', $settings->albumNameTemplate);
        $this->assertSame('{exhibitor.slug}', $settings->albumSlugTemplate);
    }

    public function test_resolve_album_identity_shortens_legacy_templates_when_nested(): void
    {
        $settings = new PluginGallerySettings(
            enabled: true,
            rootParentId: null,
            autoCreateGroup: true,
            groupNameTemplate: '{event.title}',
            groupSlugTemplate: '{event.slug}',
            autoCreateAlbum: true,
            albumNameTemplate: '{exhibitor.company_name} — {event.title}',
            albumSlugTemplate: '{exhibitor.slug}-{event.slug}',
        );

        $tokens = [
            'exhibitor.company_name' => 'Yamaha',
            'exhibitor.slug' => 'yamaha',
            'event.title' => 'Soundmit 2026 - International Sound Summit',
            'event.slug' => 'soundmit-2026-international-sound-summit',
        ];

        [$name, $slug] = $settings->resolveAlbumIdentity($tokens, nestedUnderEventGroup: true);

        $this->assertSame('Yamaha', $name);
        $this->assertSame('yamaha', $slug);
    }

    public function test_resolve_album_identity_keeps_legacy_templates_when_not_nested(): void
    {
        $settings = new PluginGallerySettings(
            enabled: true,
            rootParentId: null,
            autoCreateGroup: false,
            groupNameTemplate: '{event.title}',
            groupSlugTemplate: '{event.slug}',
            autoCreateAlbum: true,
            albumNameTemplate: '{exhibitor.company_name} — {event.title}',
            albumSlugTemplate: '{exhibitor.slug}-{event.slug}',
        );

        $tokens = [
            'exhibitor.company_name' => 'Yamaha',
            'exhibitor.slug' => 'yamaha',
            'event.title' => 'Soundmit 2026',
            'event.slug' => 'soundmit-2026',
        ];

        [$name, $slug] = $settings->resolveAlbumIdentity($tokens, nestedUnderEventGroup: false);

        $this->assertSame('Yamaha — Soundmit 2026', $name);
        $this->assertSame('yamaha-soundmit-2026', $slug);
    }
}
