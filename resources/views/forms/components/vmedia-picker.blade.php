@php
    /** @var list<array{uuid: string, name: string, thumb: string|null, type: string}> $selected */
    $selected = $selected ?? [];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['vmedia-picker space-y-3'])
        }}
    >
        @if ($selected === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('vmedia::admin.picker.empty') }}
            </p>
        @else
            <ul class="flex flex-wrap gap-3">
                @foreach ($selected as $item)
                    <li class="flex w-28 flex-col gap-1">
                        <div class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                            @if (filled($item['thumb']))
                                <img
                                    src="{{ $item['thumb'] }}"
                                    alt="{{ $item['name'] }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex h-full items-center justify-center text-xs uppercase text-gray-500">
                                    {{ $item['type'] }}
                                </div>
                            @endif
                        </div>
                        <span class="truncate text-xs text-gray-700 dark:text-gray-200" title="{{ $item['name'] }}">
                            {{ $item['name'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-dynamic-component>
