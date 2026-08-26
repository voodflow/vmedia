{{-- Shared lightbox: expect Alpine scope with `preview`, `closePreview()` --}}
<template x-teleport="body">
    <div
        x-show="preview"
        x-cloak
        class="fixed inset-0 isolate z-[9999] h-[100dvh] w-screen"
        role="dialog"
        aria-modal="true"
        style="z-index: 9999"
    >
        <div
            class="absolute inset-0 bg-gray-950/50 transition-opacity duration-300 dark:bg-gray-950/75"
            x-show="preview"
            x-transition.opacity.duration.300ms
            @click="closePreview()"
        ></div>

        <div class="relative z-10 flex h-full w-full items-center justify-center p-4 sm:p-8 pointer-events-none">
            <div
                x-show="preview"
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                class="pointer-events-auto relative flex max-h-[min(90dvh,100%)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                @click.stop
            >
                <button
                    type="button"
                    class="absolute end-3 top-3 z-20 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-950/70 text-white hover:bg-gray-950/90"
                    @click="closePreview()"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                </button>

                <template x-if="preview">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto p-4 sm:p-6">
                            <template x-if="preview.type === 'image'">
                                <img
                                    :src="preview.src || preview.thumb"
                                    :alt="preview.name"
                                    class="mx-auto block h-auto max-h-[min(75dvh,calc(100dvh-8rem))] w-auto max-w-full object-contain"
                                />
                            </template>
                            <template x-if="preview.type === 'video'">
                                <video
                                    :src="preview.src"
                                    :poster="preview.thumb || null"
                                    controls
                                    class="mx-auto block h-auto max-h-[min(75dvh,calc(100dvh-8rem))] w-auto max-w-full rounded-lg bg-black object-contain"
                                ></video>
                            </template>
                            <template x-if="preview.type !== 'image' && preview.type !== 'video'">
                                <div class="flex min-h-48 flex-col items-center justify-center gap-3 p-8 text-center">
                                    <div
                                        class="vmedia-file-icon vmedia-file-icon--lg"
                                        :class="'vmedia-file-icon--' + (preview.icon || 'file')"
                                        role="img"
                                        :aria-label="preview.icon_label || 'FILE'"
                                    >
                                        <span class="vmedia-file-icon__sheet" aria-hidden="true"></span>
                                        <span class="vmedia-file-icon__badge" x-text="preview.icon_label || 'FILE'"></span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300" x-text="preview.name"></p>
                                    <a
                                        :href="preview.src"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-sm font-medium text-primary-600 hover:underline"
                                    >
                                        {{ __('vmedia::admin.library.open') }}
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
