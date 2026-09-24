<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Voodflow\Vmedia\Support\ConversionLadder;

/**
 * Singleton JSON settings row for VoodMedia (conversion ladder, defaults).
 */
class VmediaSettings extends Model
{
    protected $fillable = [
        'data',
    ];

    public function getTable(): string
    {
        return (string) config('vmedia.tables.settings', 'vmedia_settings');
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * @return array{variants: list<array{key: string, label: string, width: int, height: int, format: string, role: string}>, default_display: string}
     */
    public static function defaults(): array
    {
        $variants = ConversionLadder::defaults();

        return [
            'variants' => $variants,
            'default_display' => (string) config('vmedia.conversions.default_display', 'lg'),
        ];
    }

    /**
     * @return array{variants: list<array{key: string, label: string, width: int, height: int, format: string, role: string}>, default_display: string}
     */
    public static function data(): array
    {
        return Cache::rememberForever('vmedia.settings', function (): array {
            $record = static::canonicalRecord();

            if ($record === null) {
                return static::defaults();
            }

            return static::normalize(is_array($record->data) ? $record->data : []);
        });
    }

    public static function canonicalRecord(): ?self
    {
        try {
            return static::query()->orderBy('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(static::data(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveData(array $data): void
    {
        $normalized = static::normalize($data);
        $record = static::canonicalRecord();

        if ($record === null) {
            static::query()->create(['data' => $normalized]);
        } else {
            $record->update(['data' => $normalized]);
        }

        Cache::forget('vmedia.settings');
    }

    public static function forgetCache(): void
    {
        Cache::forget('vmedia.settings');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{variants: list<array{key: string, label: string, width: int, height: int, format: string, role: string}>, default_display: string}
     */
    public static function normalize(array $data): array
    {
        $defaults = static::defaults();
        $variants = ConversionLadder::normalize(
            is_array($data['variants'] ?? null) ? $data['variants'] : $defaults['variants'],
        );

        if ($variants === []) {
            $variants = $defaults['variants'];
        }

        $defaultDisplay = trim((string) ($data['default_display'] ?? $defaults['default_display']));
        $keys = array_column($variants, 'key');

        if ($defaultDisplay === '' || ! in_array($defaultDisplay, $keys, true)) {
            $defaultDisplay = $defaults['default_display'];

            if (! in_array($defaultDisplay, $keys, true)) {
                foreach ($variants as $variant) {
                    if ($variant['role'] === ConversionLadder::ROLE_DISPLAY) {
                        $defaultDisplay = $variant['key'];
                        break;
                    }
                }

                $defaultDisplay = $defaultDisplay !== '' ? $defaultDisplay : ($keys[0] ?? 'thumb');
            }
        }

        return [
            'variants' => $variants,
            'default_display' => $defaultDisplay,
        ];
    }
}
