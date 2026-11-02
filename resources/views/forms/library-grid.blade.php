@php
    $images = $getImages();
    $statePath = $getStatePath();
    $pageStatePath = $getPageStatePath();
    $isMultiple = $isMultiple();
    $selectedKeys = $getSelectedKeys();
    $page = $images->currentPage();
    $lastPage = $images->lastPage();
    $onPageKeys = $images->map(fn ($image): string => $fileKey($image))->all();
    $offPageSelected = array_values(array_diff($selectedKeys, $onPageKeys));
    $inputType = $isMultiple ? 'checkbox' : 'radio';
    $groupRole = $isMultiple ? 'group' : 'radiogroup';
    $isDocument = $isDocument();
@endphp

<style>
    .ei-library-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.5rem;
        max-height: 22rem;
        overflow-y: auto;
        padding: 0.125rem;
    }

    @media (min-width: 40rem) {
        .ei-library-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    .ei-library-grid__item {
        position: relative;
        display: block;
        margin: 0;
        cursor: pointer;
    }

    .ei-library-grid__item input {
        position: absolute;
        inset: 0;
        z-index: 2;
        margin: 0;
        cursor: pointer;
        opacity: 0;
    }

    .ei-library-grid__thumb {
        position: relative;
        display: block;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        border-radius: 0.5rem;
        background-color: rgba(var(--gray-100), 1);
        box-shadow: inset 0 0 0 1px rgba(var(--gray-950), 0.1);
        transition: box-shadow 150ms ease;
    }

    .dark .ei-library-grid__thumb {
        background-color: rgba(255, 255, 255, 0.08);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    }

    .ei-library-grid__thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ei-library-grid__item:hover .ei-library-grid__thumb {
        box-shadow: inset 0 0 0 2px rgba(var(--primary-500), 0.55);
    }

    .ei-library-grid__item input:checked + .ei-library-grid__thumb,
    .ei-library-grid__item:has(input:checked) .ei-library-grid__thumb {
        box-shadow:
            inset 0 0 0 2px rgb(var(--primary-600)),
            0 0 0 2px rgba(var(--primary-600), 0.28);
    }

    .ei-library-grid__item input:checked + .ei-library-grid__thumb::after,
    .ei-library-grid__item:has(input:checked) .ei-library-grid__thumb::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(var(--primary-600), 0.18);
        pointer-events: none;
    }

    .ei-library-grid__check {
        position: absolute;
        top: 0.25rem;
        right: 0.25rem;
        z-index: 1;
        display: none;
        height: 1.25rem;
        width: 1.25rem;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: rgb(var(--primary-600));
        color: #fff;
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.7);
    }

    .ei-library-grid__item input:checked + .ei-library-grid__thumb .ei-library-grid__check,
    .ei-library-grid__item:has(input:checked) .ei-library-grid__check {
        display: flex;
    }

    .ei-library-grid-pagination {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-top: 0.75rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: rgb(var(--gray-500));
    }

    .ei-library-grid-pagination__buttons {
        display: flex;
        gap: 0.5rem;
    }

    .ei-library-grid-pagination__button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 2rem;
        padding: 0 0.5rem;
        border: 0;
        border-radius: 0.5rem;
        background: rgba(var(--gray-100), 1);
        color: rgb(var(--gray-700));
        cursor: pointer;
    }

    .ei-library-grid-pagination__button:disabled {
        cursor: not-allowed;
        opacity: 0.45;
    }

    .dark .ei-library-grid-pagination__button {
        background: rgba(255, 255, 255, 0.08);
        color: rgb(var(--gray-200));
    }

    .ei-library-files {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        max-height: 22rem;
        overflow-y: auto;
    }

    .ei-library-files__item {
        position: relative;
        display: block;
        margin: 0;
        cursor: pointer;
    }

    .ei-library-files__item input {
        position: absolute;
        inset: 0;
        z-index: 2;
        margin: 0;
        cursor: pointer;
        opacity: 0;
    }

    .ei-library-files__row {
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

    .dark .ei-library-files__row {
        background-color: rgba(255, 255, 255, 0.08);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        color: rgb(var(--gray-200));
    }

    .ei-library-files__item:hover .ei-library-files__row {
        box-shadow: inset 0 0 0 2px rgba(var(--primary-500), 0.55);
    }

    .ei-library-files__item input:checked + .ei-library-files__row {
        box-shadow:
            inset 0 0 0 2px rgb(var(--primary-600)),
            0 0 0 2px rgba(var(--primary-600), 0.28);
    }

    .ei-library-files__name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }

    .ei-library-files__meta {
        margin-inline-start: auto;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 40%;
        color: rgb(var(--gray-500));
        font-size: 0.75rem;
    }
</style>

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @if ($images->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $pickerTranslation('empty_library') }}
        </p>
    @else
        @if ($isDocument)
            <div class="ei-library-files" role="{{ $groupRole }}" aria-label="{{ $pickerTranslation('library_files') }}" wire:key="library-grid-page-{{ $page }}">
                @foreach ($offPageSelected as $key)
                    <input
                        type="{{ $inputType }}"
                        class="sr-only"
                        name="{{ $statePath }}{{ $isMultiple ? '[]' : '' }}"
                        value="{{ $key }}"
                        checked
                        {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
                    />
                @endforeach

                @foreach ($images as $image)
                    <label class="ei-library-files__item" wire:key="library-file-{{ $fileKey($image) }}">
                        <input
                            type="{{ $inputType }}"
                            name="{{ $statePath }}{{ $isMultiple ? '[]' : '' }}"
                            value="{{ $fileKey($image) }}"
                            @checked(in_array($fileKey($image), $selectedKeys, true))
                            {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
                        />
                        <span class="ei-library-files__row">
                            <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 shrink-0" />
                            <span class="ei-library-files__name" title="{{ $image->file_name }}">{{ $image->name ?: $image->file_name }}</span>
                            <span class="ei-library-files__meta">{{ $image->file_name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
        <div class="ei-library-grid" role="{{ $groupRole }}" aria-label="{{ $pickerTranslation('library_files') }}" wire:key="library-grid-page-{{ $page }}">
            @foreach ($offPageSelected as $key)
                <input
                    type="{{ $inputType }}"
                    class="sr-only"
                    name="{{ $statePath }}{{ $isMultiple ? '[]' : '' }}"
                    value="{{ $key }}"
                    checked
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
                />
            @endforeach

            @foreach ($images as $image)
                <label class="ei-library-grid__item" wire:key="library-image-{{ $fileKey($image) }}">
                    <input
                        type="{{ $inputType }}"
                        name="{{ $statePath }}{{ $isMultiple ? '[]' : '' }}"
                        value="{{ $fileKey($image) }}"
                        @checked(in_array($fileKey($image), $selectedKeys, true))
                        {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
                    />

                    <span class="ei-library-grid__thumb">
                        <img
                            src="{{ $fileUrl($image) }}"
                            alt="{{ $image->file_name }}"
                            title="{{ $image->name ?: $image->file_name }}"
                        />

                        <span class="ei-library-grid__check" aria-hidden="true">
                            <x-filament::icon icon="heroicon-m-check" class="h-3.5 w-3.5" />
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @endif

        @if ($images->hasPages())
            <nav class="ei-library-grid-pagination" aria-label="{{ $pickerTranslation('library_pagination') }}">
                <span>
                    {{ $pickerTranslation('page_of', [
                        'page' => $page,
                        'last' => $lastPage,
                        'count' => $images->total(),
                    ]) }}
                </span>

                <div class="ei-library-grid-pagination__buttons">
                    <button
                        type="button"
                        class="ei-library-grid-pagination__button"
                        wire:click="$set('{{ $pageStatePath }}', {{ $page - 1 }})"
                        @disabled($images->onFirstPage())
                    >
                        {{ $pickerTranslation('previous') }}
                    </button>
                    <button
                        type="button"
                        class="ei-library-grid-pagination__button"
                        wire:click="$set('{{ $pageStatePath }}', {{ $page + 1 }})"
                        @disabled($images->onLastPage())
                    >
                        {{ $pickerTranslation('next') }}
                    </button>
                </div>
            </nav>
        @endif
    @endif
</x-dynamic-component>
