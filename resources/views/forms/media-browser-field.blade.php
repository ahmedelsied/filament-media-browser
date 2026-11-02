@php
    $isMultiple = $isMultiple();
    $isDocument = $isDocument();
    $media = $getCurrentMedia();
    $mediaItems = $getCurrentMediaItems();
    $pendingUploads = $getPendingUploads();
    $displayItems = $getDisplayItems();
    $previewUrl = $getDisplayPreviewUrl();
    $hasSelection = $displayItems !== [] || filled($previewUrl);
    $previewAlt = ($displayItems[0]['name'] ?? null) ?: $pickerTranslation('selected');
    $isDisabled = $isDisabled();
    $removeItemAction = $isDisabled ? null : $getAction('removeItem');
@endphp

<style>
    .ei-image-picker {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        width: 100%;
    }

    .ei-image-picker__preview {
        width: 100%;
        aspect-ratio: 16 / 9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 0.5rem;
        background-color: rgba(var(--gray-100), 1);
        box-shadow: inset 0 0 0 1px rgba(var(--gray-950), 0.1);
    }

    .dark .ei-image-picker__preview {
        background-color: rgba(255, 255, 255, 0.08);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    }

    button.ei-image-picker__preview {
        padding: 0;
        border: 0;
        cursor: zoom-in;
    }

    button.ei-image-picker__preview:hover img,
    button.ei-image-picker__preview:focus-visible img {
        opacity: 0.92;
    }

    button.ei-image-picker__preview:focus-visible {
        outline: 2px solid rgb(var(--primary-600));
        outline-offset: 2px;
    }

    .ei-image-picker__preview img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        pointer-events: none;
    }

    .ei-image-picker__strip {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(6.5rem, 1fr));
        gap: 0.5rem;
        width: 100%;
    }

    .ei-image-picker__strip-item-wrap {
        position: relative;
    }

    .ei-image-picker__strip-item {
        aspect-ratio: 1 / 1;
        overflow: hidden;
        border-radius: 0.5rem;
        background-color: rgba(var(--gray-100), 1);
        box-shadow: inset 0 0 0 1px rgba(var(--gray-950), 0.1);
    }

    .ei-image-picker__strip-item img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        pointer-events: none;
    }

    button.ei-image-picker__strip-item {
        width: 100%;
        padding: 0;
        border: 0;
        cursor: zoom-in;
    }

    .ei-image-picker__strip-item-wrap .ei-image-picker__item-remove {
        position: absolute;
        top: 0.25rem;
        inset-inline-end: 0.25rem;
        z-index: 1;
        display: flex;
        border-radius: 9999px;
        background: rgba(15, 23, 42, 0.72);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .ei-image-picker__files {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        width: 100%;
    }

    .ei-image-picker__file {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        background-color: rgba(var(--gray-100), 1);
        box-shadow: inset 0 0 0 1px rgba(var(--gray-950), 0.1);
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: rgb(var(--gray-700));
    }

    .dark .ei-image-picker__file {
        background-color: rgba(255, 255, 255, 0.08);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        color: rgb(var(--gray-200));
    }

    .ei-image-picker__file-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ei-image-picker__file .ei-image-picker__item-remove {
        margin-inline-start: auto;
        flex-shrink: 0;
    }

    .ei-image-picker__preview-icon {
        width: 2.5rem;
        height: 2.5rem;
        color: rgb(var(--gray-400));
    }

    .ei-image-picker__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .ei-image-picker__filename {
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.75rem;
        line-height: 1rem;
        color: rgb(var(--gray-500));
    }

    .ei-image-picker-lightbox {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: rgba(15, 23, 42, 0.82);
    }

    .ei-image-picker-lightbox img {
        display: block;
        max-width: min(96vw, 100%);
        max-height: 90vh;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 0.5rem;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
    }

    .ei-image-picker-lightbox__close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        padding: 0;
        border: 0;
        border-radius: 9999px;
        background: rgba(15, 23, 42, 0.85);
        color: #fff;
        cursor: pointer;
    }

    [x-cloak] {
        display: none !important;
    }
</style>

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        class="ei-image-picker"
        x-data="{ open: null, urls: {{ \Illuminate\Support\Js::from(array_column($displayItems, 'preview_url')) }} }"
        @keydown.escape.window="open = null"
    >
        @if ($hasSelection && $isDocument)
            <div class="ei-image-picker__files">
                @forelse ($displayItems as $item)
                    <div class="ei-image-picker__file">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 shrink-0" />
                        <span class="ei-image-picker__file-name" title="{{ $item['name'] ?: $previewAlt }}">{{ $item['name'] ?: $previewAlt }}</span>
                        @if ($isMultiple && $removeItemAction)
                            <span class="ei-image-picker__item-remove">
                                {{ $removeItemAction(['source' => $item['source'], 'index' => $item['index']]) }}
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="ei-image-picker__file">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 shrink-0" />
                        <span class="ei-image-picker__file-name">{{ $previewAlt }}</span>
                    </div>
                @endforelse
            </div>
        @elseif ($hasSelection && $isMultiple)
            <div class="ei-image-picker__strip">
                @foreach ($displayItems as $index => $item)
                    <div class="ei-image-picker__strip-item-wrap">
                        @if (filled($item['preview_url']))
                            <button
                                type="button"
                                class="ei-image-picker__strip-item"
                                title="{{ $pickerTranslation('view_full') }}"
                                aria-label="{{ $pickerTranslation('view_full') }}"
                                @click="open = {{ $index }}"
                            >
                                <img src="{{ $item['preview_url'] }}" alt="{{ $item['name'] ?: $previewAlt }}">
                            </button>
                        @else
                            <div class="ei-image-picker__strip-item">
                                <x-filament::icon icon="heroicon-o-photo" class="ei-image-picker__preview-icon" />
                            </div>
                        @endif
                        @if ($removeItemAction)
                            <span class="ei-image-picker__item-remove">
                                {{ $removeItemAction(['source' => $item['source'], 'index' => $item['index']]) }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif ($hasSelection)
            <button
                type="button"
                class="ei-image-picker__preview"
                title="{{ $pickerTranslation('view_full') }}"
                aria-label="{{ $pickerTranslation('view_full') }}"
                @click="open = 0"
            >
                <img src="{{ $previewUrl }}" alt="{{ $previewAlt }}">
            </button>
        @else
            <div class="ei-image-picker__preview">
                <x-filament::icon
                    :icon="$isDocument ? 'heroicon-o-document-text' : 'heroicon-o-photo'"
                    class="ei-image-picker__preview-icon"
                />
            </div>
        @endif

        @unless ($isDisabled)
            <div class="ei-image-picker__actions">
                {{ $getAction('pick') }}

                @if ($hasSelection && ! $isMultiple)
                    {{ $getAction('remove') }}
                @endif
            </div>
        @endunless

        @if ($pendingUploads !== [])
            <p class="ei-image-picker__filename">
                {{ $pickerTranslation('new_upload_hint') }}
            </p>
        @elseif ($isMultiple && $mediaItems !== [])
            <p class="ei-image-picker__filename" title="{{ collect($mediaItems)->pluck('file_name')->implode(', ') }}">
                {{ collect($mediaItems)->pluck('file_name')->implode(', ') }}
            </p>
        @elseif ($media)
            <p class="ei-image-picker__filename" title="{{ $media->file_name }}">
                {{ $media->file_name }}
            </p>
        @endif

        @if ($hasSelection && ! $isDocument)
            <template x-teleport="body">
                <div
                    class="ei-image-picker-lightbox"
                    x-cloak
                    x-show="open !== null"
                    x-transition.opacity
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ $pickerTranslation('full') }}"
                    @click.self="open = null"
                >
                    <button
                        type="button"
                        class="ei-image-picker-lightbox__close"
                        aria-label="{{ $pickerTranslation('close_full') }}"
                        @click="open = null"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>

                    <img :src="urls[open] ?? ''" alt="{{ $previewAlt }}">
                </div>
            </template>
        @endif
    </div>
</x-dynamic-component>
