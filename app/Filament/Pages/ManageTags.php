<?php

namespace App\Filament\Pages;

use App\Models\Value;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class ManageTags extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.manage-tags';

    #[Locked]
    public ?string $selectedTag = null;

    #[Locked]
    public bool $isCreatingTag = false;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public function mount(): void
    {
        Gate::authorize('manage-catalog');
    }

    public function getTitle(): string
    {
        return __('tags.title');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('createTag')->label(__('tags.new_tag'))->icon(Heroicon::OutlinedPlus)
            ->extraAttributes(['data-tag-transition' => true])->action(fn () => $this->createTag())];
    }

    public function table(Table $table): Table
    {
        return $table->records(function (?string $search): Collection {
            Gate::authorize('manage-catalog');
            $counts = [];
            foreach (Value::query()->whereNotNull('tags')->pluck('tags') as $tags) {
                foreach (array_unique($tags) as $tag) {
                    $key = hash('sha256', $tag);
                    $counts[$key] ??= ['tag' => $tag, 'usage' => 0];
                    $counts[$key]['usage']++;
                }
            }

            return collect($counts)->filter(fn (array $row): bool => blank($search) || Str::contains(Str::lower($row['tag']), Str::lower($search)))->sortBy('tag', SORT_NATURAL | SORT_FLAG_CASE);
        })->columns([
            TextColumn::make('tag')->label(__('tags.tag'))->wrap(),
            TextColumn::make('usage')->label(__('tags.usage'))->numeric(),
        ])->searchable()->paginated(false)->recordAction('selectTag')->recordActions([])
            ->recordClasses(fn (array $record): ?string => $record['tag'] === $this->selectedTag ? 'catalog-selected-row' : null);
    }

    public function selectTag(string $key): void
    {
        Gate::authorize('manage-catalog');
        $record = $this->getTableRecord($key);
        abort_unless($record !== null, 404);
        $this->selectedTag = $record['tag'];
        $this->isCreatingTag = false;
    }

    public function createTag(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedTag = null;
        $this->isCreatingTag = true;
    }

    #[On('close-tag-workspace')]
    public function closeTag(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedTag = null;
        $this->isCreatingTag = false;
    }

    #[On('tag-workspace-selected')]
    public function followTag(string $tag): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedTag = $tag;
        $this->isCreatingTag = false;
        $this->flushCachedTableRecords();
    }

    #[On('catalog-tags-updated')]
    public function refreshTags(): void
    {
        Gate::authorize('manage-catalog');
        $this->flushCachedTableRecords();
    }
}
