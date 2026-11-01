<?php

namespace App\Filament\Resources;

use App\Services\CatalogAssetRecordSearch;
use App\Services\CatalogAssetRecordSequence;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait InteractsWithCatalogImageBrowsing
{
    protected ?array $imageSearchBeforeUpdate = null;

    protected ?array $imageChecksBeforeUpdate = null;

    protected ?string $imageSortBeforeUpdate = null;

    protected function searchDefaults(): array
    {
        $defaults = app(CatalogAssetRecordSearch::class)->defaults($this->imageEntity());
        unset($defaults['exact_codes']);

        return $defaults;
    }

    public function imageBrowserContext(): array
    {
        return ['target' => $this->imageTarget, 'mode' => $this->imageNavigationMode, 'record' => $this->imageRecordId, 'revision' => $this->imageBrowserRevision];
    }

    protected function admitImageContext(array $expectedContext): void
    {
        $this->authorizeImageBrowser();
        if ($expectedContext !== $this->imageBrowserContext()) {
            throw ValidationException::withMessages(['imageBrowser' => __('catalog-images.changed')]);
        }
        $this->resetValidation();
    }

    protected function changedImageBrowser(bool $resetPage = true): void
    {
        $this->imageBrowserRevision++;
        $this->imageBrowserNotice = '';
        $this->imageDisplayCache = [];
        $this->flushCachedTableRecords();
        if ($resetPage) {
            $this->resetPage($this->getTablePaginationPageName());
        }
    }

    public function updatingImageSearch(mixed $value, ?string $key = null): void
    {
        $this->authorizeImageBrowser();
        $this->imageSearchBeforeUpdate = $this->imageSearch;
    }

    public function updatedImageSearch(): void
    {
        try {
            if (array_key_exists('exact_codes', $this->imageSearch)) {
                throw ValidationException::withMessages(['imageSearch' => __('catalog-images.invalid')]);
            }
            app(CatalogAssetRecordSearch::class)->validate($this->imageSearch, $this->imageEntity());
        } catch (ValidationException $exception) {
            $this->imageSearch = $this->imageSearchBeforeUpdate ?? $this->searchDefaults();
            throw $exception;
        }
        $this->changedImageBrowser();
    }

    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        $this->authorizeImageBrowser();
        $direction ??= $column === $this->getTableSortColumn() ? match ($this->getTableSortDirection()) {
            'asc' => 'desc', 'desc' => null, default => 'asc'
        } : 'asc';
        $sort = $direction === null ? null : $column.':'.$direction;
        app(CatalogAssetRecordSearch::class)->sortDescriptor($this->imageEntity(), $sort);
        $this->tableSort = $sort;
        $this->updatedTableSort();
    }

    public function updatedTableSort(): void
    {
        $this->authorizeImageBrowser();
        try {
            $this->imageSort();
        } catch (ValidationException $exception) {
            $this->tableSort = $this->imageSortBeforeUpdate;
            throw $exception;
        }
        $this->changedImageBrowser();
    }

    public function updatingTableSort(?string $value): void
    {
        $this->authorizeImageBrowser();
        $this->imageSortBeforeUpdate = $this->tableSort;
    }

    public function switchImageTarget(string $targetKey, array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        if (! in_array($targetKey, ['product.main_image', 'group.main_image'], true)) {
            throw ValidationException::withMessages(['imageBrowser' => __('catalog-images.invalid')]);
        }
        if ($targetKey === $this->imageTarget) {
            return;
        }
        $this->forgetImagePaste();
        $this->imageTarget = $targetKey;
        $this->imageSearch = $this->searchDefaults();
        $this->imageRecordId = null;
        $this->imageNavigationMode = 'results';
        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->tableSort = null;
        $this->tableRecordsPerPage = 10;
        $this->changedImageBrowser();
        $this->resetTable();
        $this->dispatch('catalog-images-target-changed', host: $this->getId(), target: $targetKey);
    }

    public function inspectProductImage(int $id, array $expectedContext): void
    {
        $this->inspectImageRecord($id, $expectedContext, 'product.main_image');
    }

    public function inspectGroupImage(int $id, array $expectedContext): void
    {
        $this->inspectImageRecord($id, $expectedContext, 'group.main_image');
    }

    protected function inspectImageRecord(int $id, array $expectedContext, string $target): void
    {
        $this->admitImageContext($expectedContext);
        if ($target !== $this->imageTarget || $id < 1 || ! $this->imageEligibleQuery()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['imageBrowser' => __('catalog-images.changed')]);
        }
        $this->imageRecordId = $id;
        $this->changedImageBrowser(false);
        $this->dispatch('catalog-images-inspected', host: $this->getId(), context: $this->imageBrowserContext());
    }

    protected function imageSequenceQuery(): Builder
    {
        return $this->imageNavigationMode === 'checked' ? $this->imageEligibleQuery()->whereKey($this->eligibleImageChecks()) : $this->imageResultsQuery();
    }

    public function setImageNavigationMode(string $mode, array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        if (! in_array($mode, ['results', 'checked'], true)) {
            throw ValidationException::withMessages(['imageBrowser' => __('catalog-images.invalid')]);
        }
        $this->imageNavigationMode = $mode;
        $this->changedImageBrowser(false);
    }

    public function navigateImageRecord(string $direction, array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        $sequence = app(CatalogAssetRecordSequence::class);
        $query = $this->imageSequenceQuery();
        if ($this->imageRecordId !== null && ! $this->imageEligibleQuery()->whereKey($this->imageRecordId)->exists()) {
            throw ValidationException::withMessages(['imageBrowser' => __('catalog-images.changed')]);
        }
        $inside = $this->imageRecordId !== null && (clone $query)->whereKey($this->imageRecordId)->exists();
        $destination = $inside ? $sequence->neighbor($query, $this->imageRecordId, $direction, $this->imageSort(), (int) $this->getTableRecordsPerPage())
            : $sequence->boundary($query, $direction, $this->imageSort(), (int) $this->getTableRecordsPerPage());
        if ($destination === null) {
            $this->imageBrowserNotice = __('catalog-images.boundary');

            return;
        }
        if (! (clone $query)->whereKey($destination['record_id'])->exists()) {
            throw ValidationException::withMessages(['imageBrowser' => __('catalog-images.changed')]);
        }
        $visible = $this->imageNavigationMode === 'results' ? $destination : $sequence->position($this->imageResultsQuery(), $destination['record_id'], $this->imageSort(), (int) $this->getTableRecordsPerPage());
        if ($visible !== null) {
            $this->setPage($visible['page'], $this->getTablePaginationPageName());
        }
        $this->inspectImageRecord($destination['record_id'], $expectedContext, $this->imageTarget);
    }

    protected function validateImageChecks(array $values): array
    {
        Validator::make(['checks' => $values], ['checks' => ['array', 'max:5000'], 'checks.*' => ['integer', 'min:1', 'distinct']])->validate();

        return array_map('intval', array_values($values));
    }

    protected function eligibleImageChecks(): array
    {
        $ids = $this->validateImageChecks($this->selectedTableRecords);

        return $ids === [] ? [] : $this->imageEligibleQuery()->whereKey($ids)->pluck($this->imageEntity() === 'product' ? 'products.id' : 'groups.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function updatingSelectedTableRecords(mixed $value, ?string $key = null): void
    {
        $this->authorizeImageBrowser();
        $this->imageChecksBeforeUpdate = $this->selectedTableRecords;
    }

    public function updatedIsTrackingDeselectedTableRecords(): void
    {
        $this->authorizeImageBrowser();
        if ($this->isTrackingDeselectedTableRecords) {
            $this->isTrackingDeselectedTableRecords = false;
            throw ValidationException::withMessages(['checks' => __('catalog-images.explicit_checks')]);
        }
    }

    public function updatedSelectedTableRecords(): void
    {
        try {
            $this->validateImageChecks($this->selectedTableRecords);
        } catch (ValidationException $exception) {
            $this->selectedTableRecords = $this->imageChecksBeforeUpdate ?? [];
            throw $exception;
        }
        $this->imageDisplayCache = [];
        $this->flushCachedTableRecords();
    }

    public function checkImagePage(array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        $this->unionImageChecks($this->getTableRecords()->pluck('id')->all());
    }

    public function checkImageMatches(array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        $this->unionImageChecks($this->imageResultsQuery()->reorder()->limit(5001)->pluck($this->imageEntity() === 'product' ? 'products.id' : 'groups.id')->all());
    }

    protected function unionImageChecks(array $ids): void
    {
        $union = array_values(array_unique([...$this->validateImageChecks($this->selectedTableRecords), ...array_map('intval', $ids)]));
        if (count($union) > 5000) {
            throw ValidationException::withMessages(['checks' => __('catalog-images.check_limit')]);
        }
        $this->selectedTableRecords = $union;
        $this->changedImageBrowser(false);
        $this->dispatch('catalog-images-checks', host: $this->getId(), ids: array_map('strval', $union));
    }

    public function clearImageChecks(array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        $this->selectedTableRecords = [];
        $this->changedImageBrowser(false);
        $this->dispatch('deselectAllTableRecords');
    }

    public function getAllSelectableTableRecordKeys(): array
    {
        $this->authorizeImageBrowser();
        $ids = $this->imageResultsQuery()->reorder()->limit(5001)->pluck($this->imageEntity() === 'product' ? 'products.id' : 'groups.id')->all();
        if (count($ids) > 5000) {
            throw ValidationException::withMessages(['checks' => __('catalog-images.check_limit')]);
        }

        return array_map('strval', $ids);
    }

    public function imageCheckedCounts(): array
    {
        $ids = $this->eligibleImageChecks();
        $inside = $ids === [] ? 0 : $this->imageResultsQuery()->whereKey($ids)->count();

        return ['inside' => $inside, 'outside' => count($ids) - $inside];
    }

    public function getTableRecordsPerPage(): int|string|null
    {
        $value = $this->tableRecordsPerPage;

        return (is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]{0,2}$/D', $value))) && (int) $value <= 500 && (int) $value > 0 ? (int) $value : 10;
    }

    public function setImagePageSize(mixed $size, array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        $next = $this->validateImagePageSize($size);
        $ordinal = ((int) $this->getTablePage() - 1) * (int) $this->getTableRecordsPerPage();
        $total = $this->imageResultsQuery()->count();
        $this->tableRecordsPerPage = $next;
        $this->changedImageBrowser(false);
        $this->setPage(min(intdiv(max(0, $ordinal), $next) + 1, max(1, (int) ceil($total / $next))), $this->getTablePaginationPageName());
    }

    protected function validateImagePageSize(mixed $size): int
    {
        if ((! is_int($size) && ! is_string($size)) || preg_match('/^[1-9][0-9]{0,2}$/D', (string) $size) !== 1 || (int) $size > 500) {
            throw ValidationException::withMessages(['imagePageSize' => __('catalog-images.page_limit')]);
        }

        return (int) $size;
    }

    public function imagePageOptions(): array
    {
        $options = [5, 10, 15, 25, 50, 100, 200, (int) $this->getTableRecordsPerPage()];
        sort($options);

        return array_values(array_unique($options));
    }

    public function getTablePerPageSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'perPage');
    }

    public function imageBrowserProfile(): array
    {
        $this->authorizeImageBrowser();

        return ['scope' => TableBrowserProfile::scope($this), 'search' => $this->imageSearch, 'sort' => $this->tableSort,
            'perPage' => (int) $this->getTableRecordsPerPage(), 'columns' => array_map(fn ($column): array => ['name' => $column['name'], 'isToggled' => (bool) $column['isToggled']], $this->tableColumns)];
    }

    public function restoreTableBrowserProfile(array $state): void
    {
        $this->authorizeImageBrowser();
        Validator::make(['profile' => $state, 'bytes' => strlen(json_encode($state, JSON_THROW_ON_ERROR))], [
            'bytes' => ['integer', 'max:32768'], 'profile' => ['array:scope,search,sort,perPage,columns'], 'profile.scope' => ['required', 'in:'.TableBrowserProfile::scope($this)],
            'profile.search' => ['required', 'array'], 'profile.sort' => ['present', 'nullable', 'string'], 'profile.perPage' => ['required'],
            'profile.columns' => ['required', 'array', 'max:30'], 'profile.columns.*' => ['array:name,isToggled'],
            'profile.columns.*.name' => ['required', 'string', 'distinct'], 'profile.columns.*.isToggled' => ['required', 'boolean'],
        ])->validate();
        if (array_key_exists('exact_codes', $state['search'])) {
            throw ValidationException::withMessages(['profile' => __('catalog-images.invalid')]);
        }
        $recipe = app(CatalogAssetRecordSearch::class)->validate($state['search'], $this->imageEntity());
        $size = $this->validateImagePageSize($state['perPage']);
        app(CatalogAssetRecordSearch::class)->sortDescriptor($this->imageEntity(), $state['sort']);
        $defaults = collect($this->getDefaultTableColumnState())->keyBy('name');
        foreach ($state['columns'] as $column) {
            if (! $defaults->has($column['name'])) {
                throw ValidationException::withMessages(['profile' => __('catalog-images.invalid')]);
            }
        }
        $columns = [];
        foreach ($state['columns'] as $column) {
            $native = $defaults->pull($column['name']);
            if ($native['isToggleable']) {
                $native['isToggled'] = (bool) $column['isToggled'];
            }
            $columns[] = $native;
        }
        unset($recipe['terms'], $recipe['excluded'], $recipe['codes'], $recipe['exact_codes']);
        $this->imageSearch = $recipe;
        $this->tableRecordsPerPage = $size;
        $this->tableSort = $state['sort'];
        $this->applyTableColumnManager([...$columns, ...$defaults->values()->all()], true);
        $this->changedImageBrowser();
    }

    protected function requireProductPaste(array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        if ($this->imageTarget !== 'product.main_image') {
            throw ValidationException::withMessages(['exactCodePaste' => __('catalog-images.invalid')]);
        }
    }

    public function beginExactCodePaste(array $expectedContext): array
    {
        $this->requireProductPaste($expectedContext);

        return $this->withImagePasteLock(function (): array {
            $control = Cache::get($this->imagePasteControlKey(), []);
            $identity = ['token' => Str::random(40), 'session' => (string) Str::uuid(), 'revision' => max($this->codePasteRevision, $control['revision'] ?? 0) + 1];
            $this->codePasteRevision = $identity['revision'];
            $this->putImagePaste($this->imagePasteKey($identity), ['identity' => $identity, 'raw' => '', 'bytes' => 0], 10);
            $this->putImagePaste($this->imagePasteControlKey(), [...$control, 'pending' => $identity, 'revision' => $identity['revision']], 30);
            if (isset($control['pending'])) {
                Cache::forget($this->imagePasteKey($control['pending']));
            }
            $this->pendingCodePaste = $identity;

            return $identity;
        });
    }

    public function appendExactCodePaste(array $identity, int $offset, string $chunk, array $expectedContext): array
    {
        $this->requireProductPaste($expectedContext);
        if ($identity !== $this->pendingCodePaste || ! mb_check_encoding($chunk, 'UTF-8') || strlen($chunk) < 1 || strlen($chunk) > 32768 || $offset < 0 || $offset + strlen($chunk) > 524288) {
            $this->invalidImagePaste();
        }

        return $this->withImagePasteLock(function () use ($identity, $offset, $chunk): array {
            $control = Cache::get($this->imagePasteControlKey(), []);
            $key = $this->imagePasteKey($identity);
            $entry = Cache::get($key);
            if (($control['pending'] ?? null) !== $identity || ! is_array($entry) || ($entry['identity'] ?? null) !== $identity || ! isset($entry['raw'])) {
                $this->invalidImagePaste();
            }
            $bytes = strlen($entry['raw']);
            if ($offset === $bytes) {
                $entry['raw'] .= $chunk;
                $entry['bytes'] = strlen($entry['raw']);
                $this->putImagePaste($key, $entry, 10);
            } elseif ($offset + strlen($chunk) > $bytes || substr($entry['raw'], $offset, strlen($chunk)) !== $chunk) {
                $this->invalidImagePaste();
            }

            return ['bytes' => $entry['bytes']];
        });
    }

    public function finishExactCodePaste(array $identity, array $expectedContext): array
    {
        $this->requireProductPaste($expectedContext);
        if ($identity !== $this->pendingCodePaste && $identity !== $this->exactCodeSet) {
            $this->invalidImagePaste();
        }

        return $this->withImagePasteLock(function () use ($identity): array {
            $key = $this->imagePasteKey($identity);
            $control = Cache::get($this->imagePasteControlKey(), []);
            $entry = Cache::get($key);
            if (! is_array($entry) || ($entry['identity'] ?? null) !== $identity) {
                $this->invalidImagePaste();
            }
            if (($control['ready'] ?? null) === $identity && isset($entry['codes'])) {
                $this->exactCodeSet = $identity;
                $this->pendingCodePaste = ($control['pending'] ?? null) === $identity ? null : ($control['pending'] ?? null);
                $this->imageBrowserRevision = max($this->imageBrowserRevision, $entry['browser_revision']);
                $this->flushCachedTableRecords();

                return $identity;
            }
            if (($control['pending'] ?? null) !== $identity) {
                $this->invalidImagePaste();
            }
            if (! isset($entry['codes'])) {
                if (! isset($entry['raw'])) {
                    $this->invalidImagePaste();
                }
                $recipe = app(CatalogAssetRecordSearch::class)->validate(['exact_codes' => $entry['raw']], 'product');
                if ($recipe['codes'] === []) {
                    $this->invalidImagePaste();
                }
                $entry = ['identity' => $identity, 'codes' => $recipe['codes'], 'count' => count($recipe['codes']), 'browser_revision' => $this->imageBrowserRevision + 1];
                $this->putImagePaste($key, $entry, 30);
            }
            $this->putImagePaste($this->imagePasteControlKey(), ['ready' => $identity, 'revision' => $control['revision']], 30);
            if (isset($control['ready']) && $control['ready'] !== $identity) {
                Cache::forget($this->imagePasteKey($control['ready']));
            }
            $this->exactCodeSet = $identity;
            $this->pendingCodePaste = null;
            $this->changedImageBrowser();

            return $identity;
        });
    }

    public function clearExactCodePaste(array $expectedContext): void
    {
        $this->requireProductPaste($expectedContext);
        $this->forgetImagePaste();
        $this->changedImageBrowser();
    }

    protected function forgetImagePaste(): void
    {
        $this->withImagePasteLock(function (): void {
            $control = Cache::get($this->imagePasteControlKey(), []);
            foreach ([$control['pending'] ?? null, $control['ready'] ?? null, $this->pendingCodePaste, $this->exactCodeSet] as $identity) {
                if ($identity !== null) {
                    Cache::forget($this->imagePasteKey($identity));
                }
            }
            Cache::forget($this->imagePasteControlKey());
            $this->pendingCodePaste = null;
            $this->exactCodeSet = null;
        });
    }

    protected function currentExactCodes(): array
    {
        $this->authorizeImageBrowser();
        $control = Cache::get($this->imagePasteControlKey(), []);
        $entry = $this->exactCodeSet === null || $this->imageTarget !== 'product.main_image' ? null : Cache::get($this->imagePasteKey($this->exactCodeSet));
        if (($control['ready'] ?? null) !== $this->exactCodeSet || ! is_array($entry) || ($entry['identity'] ?? null) !== $this->exactCodeSet || ! isset($entry['codes']) || ! is_array($entry['codes'])) {
            $this->imageBrowserNotice = __('catalog-images.paste_again');

            return [];
        }

        return $entry['codes'];
    }

    protected function imagePasteControlKey(): string
    {
        return 'catalog-images:paste-control:'.hash('sha256', auth()->id().':'.$this->getId().':product.main_image');
    }

    protected function withImagePasteLock(callable $operation): mixed
    {
        try {
            return Cache::lock($this->imagePasteControlKey().':lock', 5)->block(2, $operation);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['exactCodePaste' => __('catalog-images.paste_busy')]);
        }
    }

    protected function putImagePaste(string $key, array $value, int $minutes): void
    {
        if (Cache::put($key, $value, now()->addMinutes($minutes)) !== true) {
            throw ValidationException::withMessages(['exactCodePaste' => __('catalog-images.paste_storage')]);
        }
    }

    protected function imagePasteKey(array $identity): string
    {
        return 'catalog-images:paste:'.hash('sha256', json_encode([auth()->id(), $this->getId(), $this->imageTarget, $identity], JSON_THROW_ON_ERROR));
    }

    protected function invalidImagePaste(): never
    {
        throw ValidationException::withMessages(['exactCodePaste' => __('catalog-images.paste_again')]);
    }
}
