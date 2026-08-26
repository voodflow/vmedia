<div class="vmedia-public-gallery mx-auto max-w-6xl px-4 py-10">
    <header class="mb-8 space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">
            {{ $gallery->name }}
        </h1>
        @if (filled($gallery->description))
            <p class="max-w-2xl text-base text-gray-600 dark:text-gray-300">
                {{ $gallery->description }}
            </p>
        @endif
    </header>

    @if ($assets === [])
        <p class="text-sm text-gray-500">{{ __('vmedia::admin.public.empty') }}</p>
    @else
        <ul class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($assets as $asset)
                <li class="overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                    @if ($asset['type'] === 'image')
                        <img
                            src="{{ $asset['thumb'] ?? $asset['src'] }}"
                            alt="{{ $asset['alt'] ?? $asset['name'] }}"
                            @if (filled($asset['object_position'] ?? null))
                                style="object-position: {{ $asset['object_position'] }}"
                            @endif
                            class="aspect-square w-full object-cover"
                            loading="lazy"
                        />
                    @elseif ($asset['type'] === 'video')
                        <video
                            class="aspect-square w-full object-cover"
                            controls
                            preload="metadata"
                            @if (filled($asset['poster'] ?? null)) poster="{{ $asset['poster'] }}" @endif
                            src="{{ $asset['src'] }}"
                        ></video>
                    @else
                        <a
                            href="{{ $asset['src'] }}"
                            class="flex aspect-square items-center justify-center p-4 text-center text-sm font-medium text-gray-700 dark:text-gray-200"
                            target="_blank"
                            rel="noopener"
                        >
                            {{ $asset['name'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
