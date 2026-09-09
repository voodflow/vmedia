<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Closure;
use InvalidArgumentException;

/**
 * Plugin-owned vault root definitions. Companions register themselves; VoodMedia does not hardcode them.
 *
 * @phpstan-type PluginVaultDefinition array{
 *     source: string,
 *     slug: string,
 *     name: string|Closure,
 *     key: string
 * }
 */
final class PluginVaultRegistry
{
    /** @var array<string, PluginVaultDefinition> */
    private static array $definitions = [];

    public static function register(
        string $source,
        string $slug,
        string|Closure $name,
        ?string $integrationKey = null,
    ): void {
        $source = trim($source);
        $slug = trim($slug);

        if ($source === '' || $slug === '') {
            throw new InvalidArgumentException('Plugin vault source and slug are required.');
        }

        self::$definitions[$source] = [
            'source' => $source,
            'slug' => $slug,
            'name' => $name,
            'key' => $integrationKey ?? 'root:'.$slug,
        ];
    }

    public static function has(string $source): bool
    {
        return isset(self::$definitions[$source]);
    }

    /**
     * @return PluginVaultDefinition
     */
    public static function get(string $source): array
    {
        if (! isset(self::$definitions[$source])) {
            throw new InvalidArgumentException("Unknown vmedia plugin vault [{$source}]. Register it via Vmedia::registerPluginVault().");
        }

        return self::$definitions[$source];
    }

    /**
     * @return list<string>
     */
    public static function sources(): array
    {
        return array_keys(self::$definitions);
    }

    /**
     * @return list<PluginVaultDefinition>
     */
    public static function all(): array
    {
        return array_values(self::$definitions);
    }

    public static function resolveName(string|Closure $name): string
    {
        return is_string($name) ? $name : (string) $name();
    }

    public static function reset(): void
    {
        self::$definitions = [];
    }
}
