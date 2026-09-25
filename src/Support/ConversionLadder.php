<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Models\VmediaSettings;

/**
 * Configurable image conversion ladder (thumb + display sizes).
 *
 * Sources (first non-empty wins):
 * 1. Filament settings (`vmedia_settings.data.variants`)
 * 2. `config('vmedia.conversions.variants')`
 * 3. Built-in Framer-style defaults (+ legacy thumb/preview env keys)
 */
final class ConversionLadder
{
    public const ROLE_THUMB = 'thumb';

    public const ROLE_DISPLAY = 'display';

    /**
     * @return list<array{key: string, label: string, width: int, height: int, format: string, role: string}>
     */
    public static function definitions(): array
    {
        $record = VmediaSettings::canonicalRecord();

        if ($record !== null) {
            $stored = is_array($record->data) ? ($record->data['variants'] ?? null) : null;

            if (is_array($stored) && $stored !== []) {
                return self::normalize($stored);
            }
        }

        $fromConfig = config('vmedia.conversions.variants');

        if (is_array($fromConfig) && $fromConfig !== []) {
            return self::normalize($fromConfig);
        }

        return self::normalize(self::legacyDefaults());
    }

    /**
     * @return list<array{key: string, label: string, width: int, height: int, format: string, role: string}>
     */
    public static function defaults(): array
    {
        return self::normalize(self::legacyDefaults());
    }

    public static function thumbKey(): string
    {
        foreach (self::definitions() as $definition) {
            if ($definition['role'] === self::ROLE_THUMB) {
                return $definition['key'];
            }
        }

        return 'thumb';
    }

    public static function defaultDisplayKey(): string
    {
        $record = VmediaSettings::canonicalRecord();
        $configured = null;

        if ($record !== null && is_array($record->data)) {
            $configured = $record->data['default_display'] ?? null;
        }

        if (! is_string($configured) || $configured === '') {
            $configured = (string) config('vmedia.conversions.default_display', 'lg');
        }

        $keys = array_column(self::definitions(), 'key');

        if (in_array($configured, $keys, true)) {
            return $configured;
        }

        foreach (self::definitions() as $definition) {
            if ($definition['role'] === self::ROLE_DISPLAY) {
                return $definition['key'];
            }
        }

        return self::thumbKey();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_values(array_map(
            static fn (array $definition): string => $definition['key'],
            self::definitions(),
        ));
    }

    /**
     * @param  list<mixed>|array<int, mixed>  $rows
     * @return list<array{key: string, label: string, width: int, height: int, format: string, role: string}>
     */
    public static function normalize(array $rows): array
    {
        $out = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = self::sanitizeKey((string) ($row['key'] ?? ''));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $width = max(1, (int) ($row['width'] ?? 0));
            $height = max(0, (int) ($row['height'] ?? 0));
            $format = self::sanitizeFormat((string) ($row['format'] ?? 'webp'));
            $role = self::sanitizeRole((string) ($row['role'] ?? self::ROLE_DISPLAY));
            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                $label = ucfirst($key);
            }

            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'label' => $label,
                'width' => $width,
                'height' => $height,
                'format' => $format,
                'role' => $role,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            if ($a['role'] !== $b['role']) {
                return $a['role'] === self::ROLE_THUMB ? -1 : 1;
            }

            return $a['width'] <=> $b['width'];
        });

        return array_values($out);
    }

    public static function sanitizeKey(string $key): string
    {
        $key = strtolower(trim($key));

        if ($key === '' || preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $key) !== 1) {
            return '';
        }

        return $key;
    }

    /**
     * @return list<array{key: string, label: string, width: int, height: int, format: string, role: string}>
     */
    private static function legacyDefaults(): array
    {
        $thumbWidth = (int) config('vmedia.conversions.thumb.width', 400);
        $thumbHeight = (int) config('vmedia.conversions.thumb.height', 400);
        $thumbFormat = (string) config('vmedia.conversions.thumb.format', 'webp');

        $rows = [
            [
                'key' => 'thumb',
                'label' => 'Thumbnail',
                'width' => max(1, $thumbWidth),
                'height' => max(0, $thumbHeight),
                'format' => $thumbFormat,
                'role' => self::ROLE_THUMB,
            ],
            [
                'key' => 'sm',
                'label' => 'Small',
                'width' => 512,
                'height' => 0,
                'format' => 'webp',
                'role' => self::ROLE_DISPLAY,
            ],
            [
                'key' => 'md',
                'label' => 'Medium',
                'width' => 1024,
                'height' => 0,
                'format' => 'webp',
                'role' => self::ROLE_DISPLAY,
            ],
            [
                'key' => 'lg',
                'label' => 'Large',
                'width' => 2048,
                'height' => 0,
                'format' => 'webp',
                'role' => self::ROLE_DISPLAY,
            ],
            [
                'key' => 'xl',
                'label' => 'Extra large',
                'width' => 4096,
                'height' => 0,
                'format' => 'webp',
                'role' => self::ROLE_DISPLAY,
            ],
        ];

        $previewWidth = (int) config('vmedia.conversions.preview.width', 0);

        if ($previewWidth > 0) {
            $previewHeight = (int) config('vmedia.conversions.preview.height', 0);
            $previewFormat = (string) config('vmedia.conversions.preview.format', 'webp');
            $hasMd = false;

            foreach ($rows as $row) {
                if ($row['key'] === 'md' || $row['width'] === $previewWidth) {
                    $hasMd = true;

                    break;
                }
            }

            if (! $hasMd) {
                $rows[] = [
                    'key' => 'preview',
                    'label' => 'Preview',
                    'width' => $previewWidth,
                    'height' => max(0, $previewHeight),
                    'format' => $previewFormat,
                    'role' => self::ROLE_DISPLAY,
                ];
            }
        }

        return $rows;
    }

    private static function sanitizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        return in_array($format, ['webp', 'jpg', 'jpeg', 'png', 'avif'], true)
            ? ($format === 'jpeg' ? 'jpg' : $format)
            : 'webp';
    }

    private static function sanitizeRole(string $role): string
    {
        $role = strtolower(trim($role));

        return $role === self::ROLE_THUMB ? self::ROLE_THUMB : self::ROLE_DISPLAY;
    }
}
