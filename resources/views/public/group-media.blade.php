<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $gallery->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: #0b0f14; color: #f3f4f6; }
        .wrap { max-width: 72rem; margin: 0 auto; padding: 2.5rem 1rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.875rem; letter-spacing: -0.02em; }
        .desc, .breadcrumb { max-width: 40rem; color: #9ca3af; margin: 0 0 1.5rem; font-size: .875rem; }
        .breadcrumb a { color: #d1d5db; }
        .empty { color: #9ca3af; font-size: .875rem; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; list-style: none; padding: 0; margin: 0; }
        @media (min-width: 768px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .card { overflow: hidden; border-radius: 0.75rem; background: #111827; aspect-ratio: 1 / 1; }
        .card img, .card video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .file { display: flex; align-items: center; justify-content: center; height: 100%; padding: 1rem; text-align: center; color: #e5e7eb; text-decoration: none; font-size: .875rem; font-weight: 600; }
        .pager { margin-top: 2rem; display: flex; gap: 1rem; align-items: center; font-size: .875rem; color: #9ca3af; }
        .pager a { color: #f3f4f6; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>{{ $gallery->name }}</h1>
        @if (filled($gallery->description))
            <p class="desc">{{ $gallery->description }}</p>
        @endif
        <p class="breadcrumb">
            <a href="{{ url(config('vmedia.public.prefix', 'galleries').'/'.$path) }}">
                {{ __('vmedia::admin.public.view_albums') }}
            </a>
        </p>
    </header>

    @if ($assets === [])
        <p class="empty">{{ __('vmedia::admin.public.empty') }}</p>
    @else
        <ul class="grid">
            @foreach ($assets as $asset)
                <li class="card">
                    @if ($asset['type'] === 'image')
                        <img
                            src="{{ $asset['thumb'] ?? $asset['src'] }}"
                            alt="{{ $asset['alt'] ?? $asset['name'] }}"
                            @if (filled($asset['object_position'] ?? null))
                                style="object-position: {{ $asset['object_position'] }}"
                            @endif
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
                        <a class="file" href="{{ $asset['src'] }}" target="_blank" rel="noopener">
                            {{ $asset['name'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>

        @if (($meta['last_page'] ?? 1) > 1)
            <nav class="pager">
                @if (($meta['current_page'] ?? 1) > 1)
                    <a href="?mode=media&page={{ $meta['current_page'] - 1 }}">{{ __('vmedia::admin.public.prev') }}</a>
                @endif
                <span>{{ $meta['current_page'] }} / {{ $meta['last_page'] }}</span>
                @if ($meta['has_more'] ?? false)
                    <a href="?mode=media&page={{ $meta['current_page'] + 1 }}">{{ __('vmedia::admin.public.next') }}</a>
                @endif
            </nav>
        @endif
    @endif
</div>
</body>
</html>
