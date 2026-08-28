@php
    /** @var list<array{uuid: string, name: string, thumb: string|null, src: string, type: string, caption: string|null, alt: string|null, credits: string|null, has_override: bool}> $selected */
    $selected = $selected ?? [];
    /** @var list<array{uuid: string, caption: string|null, alt: string|null, credits: string|null}> $stateItems */
    $stateItems = $stateItems ?? [];
    $isMultiple = (bool) ($isMultiple ?? false);
    $componentKey = $getKey();
    $recordKey = $recordKey ?? 'new';
    $statePath = $getStatePath();
    $orderedUuids = array_column($selected, 'uuid');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['vmedia-picker'])
        }}
        wire:key="vmedia-picker-{{ $recordKey }}-{{ $componentKey }}"
        x-data="{
            preview: null,
            multiple: @js($isMultiple),
            order: @js($orderedUuids),
            catalog: @js(collect($selected)->keyBy('uuid')->all()),
            stateByUuid: @js(collect($stateItems)->keyBy('uuid')->all()),
            dragUuid: null,
            overUuid: null,
            statePath: @js($statePath),
            openPreview(asset) {
                this.preview = asset;
                document.documentElement.classList.add('overflow-y-hidden');
            },
            closePreview() {
                this.preview = null;
                document.documentElement.classList.remove('overflow-y-hidden');
            },
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
            buildState() {
                return this.order.map((uuid) => {
                    const meta = this.stateByUuid[uuid] || {};

                    return {
                        uuid,
                        caption: meta.caption ?? null,
                        alt: meta.alt ?? null,
                        credits: meta.credits ?? null,
                    };
                });
            },
            persistOrder() {
                if (! this.multiple) {
                    return;
                }
                $wire.set(this.statePath, this.buildState());
            },
            onDragStart(uuid, event) {
                if (! this.multiple) {
                    return;
                }
                this.dragUuid = uuid;
                this.overUuid = null;
                event.dataTransfer.effectAllowed = 'move';
                try { event.dataTransfer.setData('text/plain', uuid); } catch (e) {}
            },
            onDragOver(targetUuid, event) {
                if (! this.multiple || ! this.dragUuid) {
                    return;
                }
                event.preventDefault();
                if (this.dragUuid === targetUuid || this.overUuid === targetUuid) {
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
                if (! this.multiple) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                const fromUuid = this.dragUuid || event.dataTransfer.getData('text/plain');
                if (fromUuid && targetUuid && fromUuid !== targetUuid) {
                    this.order = this.moveBefore(this.order, fromUuid, targetUuid);
                }
                this.dragUuid = null;
                this.overUuid = null;
                this.persistOrder();
            },
            onDragEnd() {
                this.dragUuid = null;
                this.overUuid = null;
            },
        }"
        @keydown.escape.window="if (preview) closePreview()"
        @dragend.window="onDragEnd()"
    >
        @if ($selected === [])
            <p class="vmedia-browser__empty" style="padding: 0.5rem 0; text-align: start;">
                {{ __('vmedia::admin.picker.empty') }}
            </p>
        @else
            @if ($isMultiple)
                <p class="vmedia-picker__hint">{{ __('vmedia::admin.picker.reorder_hint') }}</p>
            @endif

            <ul class="vmedia-picker__selected">
                <template x-for="item in ordered()" :key="item.uuid">
                    <li
                        class="vmedia-picker__chip"
                        :class="{
                            'vmedia-picker__chip--has-meta': item.has_override,
                            'is-dragging': multiple && dragUuid === item.uuid,
                            'is-drop-target': multiple && overUuid === item.uuid && dragUuid !== item.uuid,
                        }"
                        :draggable="multiple ? 'true' : 'false'"
                        @dragstart="onDragStart(item.uuid, $event)"
                        @dragover="onDragOver(item.uuid, $event)"
                        @dragleave="onDragLeave(item.uuid)"
                        @drop="onDrop(item.uuid, $event)"
                        @dragend="onDragEnd()"
                    >
                        <div class="vmedia-picker__chip-body">
                            <div class="vmedia-picker__chip-thumb-wrap">
                                <button
                                    type="button"
                                    class="vmedia-picker__chip-btn"
                                    @click="openPreview(item)"
                                    :title="'{{ __('vmedia::admin.picker.preview') }}: ' + item.name"
                                >
                                    <div class="vmedia-picker__chip-thumb">
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
                                </button>

                                <div class="vmedia-picker__chip-overlay">
                                    <button
                                        type="button"
                                        class="vmedia-picker__chip-icon-btn"
                                        title="{{ __('vmedia::admin.picker.edit_item') }}"
                                        @click.stop="$wire.mountAction('editAttachmentMeta', { uuid: item.uuid }, { schemaComponent: @js($componentKey) })"
                                    >
                                        <x-filament::icon icon="heroicon-m-pencil-square" class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="vmedia-picker__chip-icon-btn vmedia-picker__chip-icon-btn--danger"
                                        title="{{ __('vmedia::admin.picker.detach') }}"
                                        @click.stop="$wire.mountAction('removeAttachment', { uuid: item.uuid }, { schemaComponent: @js($componentKey) })"
                                    >
                                        <x-filament::icon icon="heroicon-m-minus" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        @endif

        @include('vmedia::forms.components.media-preview-lightbox')
    </div>
</x-dynamic-component>
