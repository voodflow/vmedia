<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Voodflow\Vmedia\Support\GalleryProvisioner;

/**
 * Normalized gallery integration settings shared across Voodflow plugins.
 */
final class PluginGallerySettings
{
    public function __construct(
        public bool $enabled,
        public ?int $rootParentId,
        public bool $autoCreateGroup,
        public string $groupNameTemplate,
        public string $groupSlugTemplate,
        public bool $autoCreateAlbum,
        public string $albumNameTemplate,
        public string $albumSlugTemplate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $group = (array) ($data['group'] ?? $data['event_group'] ?? []);
        $album = (array) ($data['album'] ?? []);

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            rootParentId: filled($data['root_parent_id'] ?? null) ? (int) $data['root_parent_id'] : null,
            autoCreateGroup: (bool) ($group['enabled'] ?? true),
            groupNameTemplate: (string) ($group['name'] ?? '{event.title}'),
            groupSlugTemplate: (string) ($group['slug'] ?? '{event.slug}'),
            autoCreateAlbum: (bool) ($album['enabled'] ?? true),
            albumNameTemplate: (string) ($album['name'] ?? '{entity.name}'),
            albumSlugTemplate: (string) ($album['slug'] ?? '{entity.slug}'),
        );
    }

    /**
     * @param  array<string, scalar|null>  $tokens
     * @return array{0: string, 1: string}
     */
    public function resolveAlbumIdentity(array $tokens, bool $nestedUnderEventGroup): array
    {
        $name = GalleryProvisioner::renderTemplate($this->albumNameTemplate, $tokens);
        $slug = GalleryProvisioner::renderTemplate($this->albumSlugTemplate, $tokens);

        if (! $nestedUnderEventGroup || ! $this->autoCreateGroup || ! $this->usesRedundantNestedAlbumTemplate()) {
            return [$name, $slug];
        }

        $shortName = trim((string) ($tokens['entity.name'] ?? $tokens['exhibitor.company_name'] ?? $name));
        $shortSlug = (string) ($tokens['entity.slug'] ?? $tokens['exhibitor.slug'] ?? $slug);

        return [$shortName, $shortSlug];
    }

    public function usesRedundantNestedAlbumTemplate(): bool
    {
        $legacyNameTemplates = [
            '{exhibitor.company_name} — {event.title}',
            '{entity.name} — {event.title}',
        ];
        $legacySlugTemplates = [
            '{exhibitor.slug}-{event.slug}',
            '{entity.slug}-{event.slug}',
        ];

        return in_array(trim($this->albumNameTemplate), $legacyNameTemplates, true)
            || in_array(trim($this->albumSlugTemplate), $legacySlugTemplates, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'root_parent_id' => $this->rootParentId,
            'group' => [
                'enabled' => $this->autoCreateGroup,
                'name' => $this->groupNameTemplate,
                'slug' => $this->groupSlugTemplate,
            ],
            'album' => [
                'enabled' => $this->autoCreateAlbum,
                'name' => $this->albumNameTemplate,
                'slug' => $this->albumSlugTemplate,
            ],
        ];
    }
}
