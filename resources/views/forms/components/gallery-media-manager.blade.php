@php
    /** @var list<array{uuid: string, name: string, thumb: string|null, src: string, type: string, caption: string|null, alt: string|null}> $items */
    $items = $items ?? [];
    $statePath = $getStatePath();
    $componentKey = $getKey();
    $orderedUuids = array_column($items, 'uuid');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['vmedia-gallery-manager'])
        }}
        wire:key="vmedia-gallery-mgr-{{ md5(implode(',', $orderedUuids)) }}"
        x-data="{
            order: @js($orderedUuids),
            catalog: @js(collect($items)->keyBy('uuid')->all()),
            dragUuid: null,
            overUuid: null,
            statePath: @js($statePath),
            ordered() {
                return this.order
                    .map((uuid) => this.catalog[uuid] || null)
                    .filter(Boolean);
            },
            moveBefore(list, fromUuid, targetUuid) {
                const from = list.indexOf(fromUuid);
                const to = list.indexOf(targetUuid);
                if (from < 0 || to < 0 || from === to) {
                    return list;
                }
                const next = [...list];
                next.splice(from, 1);
                const insertAt = next.indexOf(targetUuid);
                next.splice(insertAt < 0 ? next.length : insertAt, 0, fromUuid);

                return next;
            },
            onDragStart(uuid, event) {
                this.dragUuid = uuid;
                this.overUuid = null;
                event.dataTransfer.effectAllowed = 'move';
                try { event.dataTransfer.setData('text/plain', uuid); } catch (e) {}
            },
            onDragOver(targetUuid, event) {
                event.preventDefault();
                if (! this.dragUuid || this.dragUuid === targetUuid || this.overUuid === targetUuid) {
                    return;
                }
                this.overUuid = targetUuid;
                this.order = this.moveBefore(this.order, this.dragUuid, targetUuid);
            },
            onDragLeave(targetUuid) {
                if (this.overUuid === targetUuid) {
                    this.overUuid = null;
                }
            },
            onDrop(targetUuid, event) {
                event.preventDefault();
                event.stopPropagation();
                const fromUuid = this.dragUuid || event.dataTransfer.getData('text/plain');
                if (fromUuid && targetUuid && fromUuid !== targetUuid) {
                    this.order = this.moveBefore(this.order, fromUuid, targetUuid);
                }
                this.dragUuid = null;
                this.overUuid = null;
                $wire.set(this.statePath, [...this.order]);
            },
            onDragEnd() {
                this.dragUuid = null;
                this.overUuid = null;
            },
        }"
        @dragend.window="onDragEnd()"
    >
        <div class="vmedia-gallery-manager__toolbar">
            <div class="vmedia-gallery-manager__actions">
                {{ $getAction('browseVault') }}
                {{ $getAction('uploadMedia') }}
                {{ $getAction('importZip') }}
            </div>
            <p class="vmedia-gallery-manager__hint">
                {{ __('vmedia::admin.galleries.media.hint') }}
            </p>
        </div>

        <template x-if="ordered().length === 0">
            <p class="vmedia-gallery-manager__empty">
                {{ __('vmedia::admin.galleries.media.empty') }}
            </p>
        </template>

        <ul class="vmedia-gallery-manager__grid">
            <template x-for="item in ordered()" :key="item.uuid">
                <li
                    class="vmedia-gallery-manager__tile"
                    :class="{
                        'is-dragging': dragUuid === item.uuid,
                        'is-drop-target': overUuid === item.uuid && dragUuid !== item.uuid,
                    }"
                    draggable="true"
                    @dragstart="onDragStart(item.uuid, $event)"
                    @dragover="onDragOver(item.uuid, $event)"
                    @dragleave="onDragLeave(item.uuid)"
                    @drop="onDrop(item.uuid, $event)"
                    @dragend="onDragEnd()"
                >
                    <div class="vmedia-gallery-manager__thumb">
                        <template x-if="item.thumb">
                            <img :src="item.thumb" alt="" draggable="false" />
                        </template>
                        <template x-if="! item.thumb">
                            <div
                                class="vmedia-file-icon"
                                :class="'vmedia-file-icon--' + (item.icon || 'file')"
                                role="img"
                                :aria-label="item.icon_label || 'FILE'"
                            >
                                <span class="vmedia-file-icon__sheet" aria-hidden="true"></span>
                                <span class="vmedia-file-icon__badge" x-text="item.icon_label || 'FILE'"></span>
                            </div>
                        </template>
                    </div>

                    <div class="vmedia-gallery-manager__overlay">
                        <button
                            type="button"
                            class="vmedia-gallery-manager__btn"
                            title="{{ __('vmedia::admin.library.edit') }}"
                            @click.stop="$wire.mountAction('editGalleryMedia', { uuid: item.uuid }, { schemaComponent: @js($componentKey) })"
                        >
                            <x-filament::icon icon="heroicon-m-pencil-square" class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="vmedia-gallery-manager__btn vmedia-gallery-manager__btn--danger"
                            title="{{ __('vmedia::admin.galleries.media.remove') }}"
                            @click.stop="$wire.mountAction('removeGalleryMedia', { uuid: item.uuid }, { schemaComponent: @js($componentKey) })"
                        >
                            <x-filament::icon icon="heroicon-m-minus" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</x-dynamic-component>
