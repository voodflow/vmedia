<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Domain model ↔ vault media pivot. Per-attachment overrides live in `properties`
 * so the same vault file can have different caption/alt/credits on each record.
 */
class MediaAttachment extends MorphPivot
{
    public $incrementing = true;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return (string) config('vmedia.tables.attachments', 'vmedia_attachments');
    }

    /**
     * @return array<string, mixed>
     */
    public function propertiesBag(): array
    {
        return is_array($this->properties) ? $this->properties : [];
    }

    public function property(string $key): ?string
    {
        $value = $this->propertiesBag()[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    public function caption(): ?string
    {
        return $this->property(MediaItem::CUSTOM_CAPTION);
    }

    public function alt(): ?string
    {
        return $this->property(MediaItem::CUSTOM_ALT);
    }

    public function credits(): ?string
    {
        return $this->property(MediaItem::CUSTOM_CREDITS);
    }
}
