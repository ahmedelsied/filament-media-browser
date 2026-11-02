<?php

namespace A0x01\FilamentMediaBrowser\Forms\Components;

use A0x01\FilamentMediaBrowser\Enums\LibraryKind;
use A0x01\FilamentMediaBrowser\MediaLibrary;
use A0x01\FilamentMediaBrowser\Support\FileKey;
use A0x01\FilamentMediaBrowser\Support\PreviewUrl;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LibraryGrid extends Field
{
    protected string $view = 'filament-media-browser::forms.library-grid';

    protected ?Closure $searchUsing = null;

    protected ?Closure $pageUsing = null;

    protected string $pageField = 'page';

    protected int|Closure $perPage = 24;

    protected bool|Closure $isMultiple = false;

    protected LibraryKind|Closure $libraryKind = LibraryKind::IMAGE;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated();
        $this->live();
    }

    public function searchUsing(?Closure $callback): static
    {
        $this->searchUsing = $callback;

        return $this;
    }

    public function pageUsing(?Closure $callback): static
    {
        $this->pageUsing = $callback;

        return $this;
    }

    public function pageField(string $name): static
    {
        $this->pageField = $name;

        return $this;
    }

    public function perPage(int|Closure $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function libraryKind(LibraryKind|Closure $kind): static
    {
        $this->libraryKind = $kind;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function isDocument(): bool
    {
        return $this->getLibraryKind() === LibraryKind::DOCUMENT;
    }

    public function getLibraryKind(): LibraryKind
    {
        $kind = $this->evaluate($this->libraryKind);

        return $kind instanceof LibraryKind ? $kind : LibraryKind::IMAGE;
    }

    public function fileKey(Model $file): string
    {
        return FileKey::of($file);
    }

    public function fileUrl(Model $file): string
    {
        return PreviewUrl::for($file);
    }

    /**
     * @return list<string>
     */
    public function getSelectedKeys(): array
    {
        $state = $this->getState();

        if (is_string($state) && $state !== '') {
            return [$state];
        }

        if (! is_array($state)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $state,
            ),
            fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function getImages(): LengthAwarePaginator
    {
        $search = $this->getSearchTerm();
        $perPage = max(1, (int) $this->evaluate($this->perPage));

        $query = MediaLibrary::resolve()->libraryQuery($this->getLibraryKind())
            ->when(
                filled($search),
                fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->where('file_name', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')),
            )
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $this->getCurrentPage()), $lastPage);

        return $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'library',
            page: $page,
        );
    }

    public function getCurrentPage(): int
    {
        if ($this->pageUsing === null) {
            return 1;
        }

        return max(1, (int) $this->evaluate($this->pageUsing));
    }

    public function getPageStatePath(): string
    {
        return (string) str($this->getStatePath())->beforeLast('.').'.'.$this->pageField;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function pickerTranslation(string $key, array $replace = []): string
    {
        $group = $this->isDocument() ? 'document' : 'image';

        return __('filament-media-browser::media-browser.picker.'.$group.'.'.$key, $replace);
    }

    protected function getSearchTerm(): ?string
    {
        if ($this->searchUsing === null) {
            return null;
        }

        $term = $this->evaluate($this->searchUsing);

        return filled($term) ? (string) $term : null;
    }
}
