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
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; list-style: none; padding: 0; margin: 0; }
        @media (min-width: 768px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .card { overflow: hidden; border-radius: 0.75rem; background: #111827; }
        .card a { color: inherit; text-decoration: none; display: block; }
        .thumb { aspect-ratio: 4 / 3; background: #1f2937; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .meta { padding: .75rem 1rem 1rem; }
        .meta h2 { margin: 0; font-size: 1rem; }
        .meta p { margin: .35rem 0 0; color: #9ca3af; font-size: .8125rem; }
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
            <a href="{{ url(config('vmedia.public.prefix', 'galleries').'/'.$path.'?mode=media') }}">
                {{ __('vmedia::admin.public.view_all_media') }}
            </a>
        </p>
    </header>

    <ul class="grid">
        @foreach ($children as $child)
            <li class="card">
                <a href="{{ url(config('vmedia.public.prefix', 'galleries').'/'.$child['path']) }}">
                    <div class="thumb">
                        @if (($child['cover']['thumb'] ?? null) || ($child['cover']['src'] ?? null))
                            <img src="{{ $child['cover']['thumb'] ?? $child['cover']['src'] }}" alt="{{ $child['name'] }}" loading="lazy" />
                        @endif
                    </div>
                    <div class="meta">
                        <h2>{{ $child['name'] }}</h2>
                        <p>{{ trans_choice('vmedia::admin.public.items', $child['media_count'], ['count' => $child['media_count']]) }}</p>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
</div>
</body>
</html>
