<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

/**
 * @phpstan-type AttachedMediaSlideArray array{url: string, alt: string, caption: string}
 */
final class AttachedMediaSlide
{
    public function __construct(
        public readonly string $url,
        public readonly string $alt = '',
        public readonly string $caption = '',
    ) {}

    /**
     * @return AttachedMediaSlideArray
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'alt' => $this->alt,
            'caption' => $this->caption,
        ];
    }
}
