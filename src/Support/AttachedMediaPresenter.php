<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
use Voodflow\Vmedia\Models\MediaItem;

/**
 * Build ordered slide arrays from {@see HasAttachedMedia} collections.
 */
final class AttachedMediaPresenter
{
    /**
     * @param  callable(MediaItem, Model): string  $altResolver
     * @param  callable(MediaItem, Model): string|null  $captionResolver
     * @return list<AttachedMediaSlide>
     */
    public static function slides(
        Model $record,
        string $collection,
        array $options = [],
        ?callable $altResolver = null,
        ?callable $captionResolver = null,
    ): array {
        if (! self::usesAttachedMedia($record)) {
            return [];
        }

        $items = self::orderedItems($record, $collection, $options);

        $slides = [];

        foreach ($items as $media) {
            $url = MediaLibrary::publicUrl($media);

            if ($url === '') {
                continue;
            }

            $alt = $altResolver !== null
                ? trim((string) $altResolver($media, $record))
                : (AttachmentMeta::alt($media) ?? (string) ($media->name ?? ''));

            $caption = $captionResolver !== null
                ? trim((string) ($captionResolver($media, $record) ?? ''))
                : (AttachmentMeta::caption($media) ?? '');

            if ($caption === '') {
                $caption = $alt;
            }

            $slides[] = new AttachedMediaSlide($url, $alt, $caption);
        }

        return $slides;
    }

    /**
     * @return list<AttachedMediaSlideArray>
     *
     * @phpstan-import-type AttachedMediaSlideArray from AttachedMediaSlide
     */
    public static function slideArrays(
        Model $record,
        string $collection,
        array $options = [],
        ?callable $altResolver = null,
        ?callable $captionResolver = null,
    ): array {
        return array_map(
            static fn (AttachedMediaSlide $slide): array => $slide->toArray(),
            self::slides($record, $collection, $options, $altResolver, $captionResolver),
        );
    }

    /**
     * Merge slides from multiple collections (order preserved per collection).
     *
     * @param  list<string>  $collections
     * @return list<AttachedMediaSlideArray>
     *
     * @phpstan-import-type AttachedMediaSlideArray from AttachedMediaSlide
     */
    public static function mergedSlideArrays(
        Model $record,
        array $collections,
        array $options = [],
        ?callable $altResolver = null,
        ?callable $captionResolver = null,
    ): array {
        $slides = [];

        foreach ($collections as $collection) {
            foreach (self::slideArrays($record, $collection, [], $altResolver, $captionResolver) as $slide) {
                $slides[] = $slide;
            }
        }

        $order = (string) ($options['order'] ?? 'manual');

        if ($order === 'random') {
            shuffle($slides);
        }

        $limit = max(0, (int) ($options['limit'] ?? 0));

        if ($limit > 0) {
            $slides = array_slice($slides, 0, $limit);
        }

        return $slides;
    }

    /**
     * @return Collection<int, MediaItem>
     */
    private static function orderedItems(Model $record, string $collection, array $options): Collection
    {
        /** @var Model&HasAttachedMedia $record */
        $items = $record->getMedia($collection);

        $order = (string) ($options['order'] ?? 'manual');

        if ($order === 'random') {
            $items = $items->shuffle();
        }

        $limit = max(0, (int) ($options['limit'] ?? 0));

        if ($limit > 0) {
            $items = $items->take($limit);
        }

        return $items->values();
    }

    /**
     * @phpstan-assert-if-true Model&HasAttachedMedia $record
     */
    private static function usesAttachedMedia(Model $record): bool
    {
        return in_array(HasAttachedMedia::class, class_uses_recursive($record), true);
    }
}
