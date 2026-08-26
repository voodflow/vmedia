<div class="vmedia-gallery-embed">
    @if (filled($gallery->name))
        <h3 class="vmedia-gallery-embed__title">{{ $gallery->name }}</h3>
    @endif
    @if (filled($gallery->description))
        <p class="vmedia-gallery-embed__desc">{{ $gallery->description }}</p>
    @endif

    @if ($assets === [])
        <p class="vmedia-gallery-embed__empty">{{ __('vmedia::admin.public.empty') }}</p>
    @else
        <ul class="vmedia-gallery-embed__grid">
            @foreach ($assets as $asset)
                <li class="vmedia-gallery-embed__item">
                    @if ($asset['type'] === 'image')
                        <img
                            src="{{ $asset['thumb'] ?? $asset['src'] }}"
                            alt="{{ $asset['alt'] ?? $asset['name'] }}"
                            loading="lazy"
                        />
                    @elseif ($asset['type'] === 'video')
                        <video
                            controls
                            preload="metadata"
                            @if (filled($asset['poster'] ?? null)) poster="{{ $asset['poster'] }}" @endif
                            src="{{ $asset['src'] }}"
                        ></video>
                    @else
                        <a href="{{ $asset['src'] }}" target="_blank" rel="noopener">{{ $asset['name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>

<style>
    .vmedia-gallery-embed__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
        gap: 0.75rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .vmedia-gallery-embed__item {
        aspect-ratio: 1 / 1;
        overflow: hidden;
        border-radius: 0.5rem;
        background: #111827;
    }
    .vmedia-gallery-embed__item img,
    .vmedia-gallery-embed__item video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .vmedia-gallery-embed__title { margin: 0 0 0.35rem; font-size: 1.125rem; }
    .vmedia-gallery-embed__desc { margin: 0 0 1rem; color: #6b7280; font-size: 0.875rem; }
    .vmedia-gallery-embed__empty { color: #6b7280; font-size: 0.875rem; }
</style>
