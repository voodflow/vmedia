@php
    /** @var list<array{uuid: string, name: string, thumb: string|null, src: string, type: string, caption: string|null, alt: string|null}> $slides */
    $slides = $slides ?? [];
@endphp

@if ($slides === [])
    <p class="text-sm text-gray-500">{{ __('vmedia::admin.galleries.media.slideshow_empty') }}</p>
@else
    <div
        x-data="{
            index: 0,
            slides: @js($slides),
            get current() { return this.slides[this.index] || null },
            prev() { this.index = (this.index - 1 + this.slides.length) % this.slides.length },
            next() { this.index = (this.index + 1) % this.slides.length },
        }"
        class="vmedia-slideshow"
        @keydown.left.window="prev()"
        @keydown.right.window="next()"
    >
        <div class="vmedia-slideshow__stage">
            <template x-if="current && current.type === 'image'">
                <img :src="current.src || current.thumb" :alt="current.alt || current.name" class="vmedia-slideshow__media" />
            </template>
            <template x-if="current && current.type === 'video'">
                <video :src="current.src" controls class="vmedia-slideshow__media"></video>
            </template>
            <template x-if="current && current.type !== 'image' && current.type !== 'video'">
                <a :href="current.src" target="_blank" rel="noopener" class="vmedia-slideshow__file">
                    <div
                        class="vmedia-file-icon vmedia-file-icon--lg"
                        :class="'vmedia-file-icon--' + (current.icon || 'file')"
                        role="img"
                        :aria-label="current.icon_label || 'FILE'"
                    >
                        <span class="vmedia-file-icon__sheet" aria-hidden="true"></span>
                        <span class="vmedia-file-icon__badge" x-text="current.icon_label || 'FILE'"></span>
                    </div>
                    <span x-text="current.name"></span>
                </a>
            </template>
        </div>

        <div class="vmedia-slideshow__bar">
            <button type="button" class="vmedia-slideshow__nav" @click="prev()">‹</button>
            <div class="vmedia-slideshow__meta">
                <p class="vmedia-slideshow__title" x-text="current?.name"></p>
                <p class="vmedia-slideshow__caption" x-show="current?.caption" x-text="current?.caption"></p>
                <p class="vmedia-slideshow__count">
                    <span x-text="index + 1"></span> / <span x-text="slides.length"></span>
                </p>
            </div>
            <button type="button" class="vmedia-slideshow__nav" @click="next()">›</button>
        </div>
    </div>

    <style>
        .vmedia-slideshow { display: flex; flex-direction: column; gap: 0.75rem; }
        .vmedia-slideshow__stage {
            display: flex; align-items: center; justify-content: center;
            min-height: 18rem; max-height: min(70vh, 36rem);
            border-radius: 0.75rem; background: #0b0f14; overflow: hidden;
        }
        .vmedia-slideshow__media {
            max-width: 100%; max-height: min(70vh, 36rem);
            object-fit: contain; display: block;
        }
        .vmedia-slideshow__file {
            display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
            color: #f9fafb; font-weight: 600; text-decoration: none;
        }
        .vmedia-slideshow__file:hover { text-decoration: underline; }
        .vmedia-slideshow__bar { display: flex; align-items: center; gap: 0.75rem; }
        .vmedia-slideshow__nav {
            width: 2.25rem; height: 2.25rem; border-radius: 9999px; border: 0;
            background: #e5e7eb; font-size: 1.25rem; cursor: pointer; flex-shrink: 0;
        }
        .vmedia-slideshow__meta { flex: 1; min-width: 0; text-align: center; }
        .vmedia-slideshow__title { margin: 0; font-size: 0.875rem; font-weight: 600; }
        .vmedia-slideshow__caption { margin: 0.15rem 0 0; font-size: 0.75rem; color: #6b7280; }
        .vmedia-slideshow__count { margin: 0.25rem 0 0; font-size: 0.7rem; color: #9ca3af; }
    </style>
@endif
