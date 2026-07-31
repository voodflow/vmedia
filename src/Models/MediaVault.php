<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Singleton Spatie owner for all library files (galleries are membership only).
 */
class MediaVault extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
    ];

    public function getTable(): string
    {
        return (string) config('voodbuilder-media.tables.vaults', 'voodbuilder_media_vaults');
    }

    public static function current(): self
    {
        $existing = static::query()->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::query()->create([
            'name' => 'Media vault',
        ]);
    }

    public function registerMediaCollections(): void
    {
        $disk = (string) config('voodbuilder-media.disk', 'public');

        $this->addMediaCollection('images')
            ->useDisk($disk);

        $this->addMediaCollection('videos')
            ->useDisk($disk);
    }
}
