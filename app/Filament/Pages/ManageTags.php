<?php

namespace App\Filament\Pages;

use App\Actions\ManageValueTags;
use App\Filament\Resources\Values\ValueResource;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ManageTags extends Page implements HasTable
{
    use InteractsWithTable {
        resolveTableRecord as protected resolveCurrentTagRecord;
    }

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.manage-tags';

    /** @var array<string, mixed> */
    #[Locked]
    public array $tagPreview = [];

    #[Locked]
    public ?string $tagActionSource = null;

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

    public static function valuesUrl(string $tag): string
    {
        return ValueResource::getUrl('index', ['filters' => ['tags' => ['values' => [$tag]]]]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('values')->label(__('tags.values'))->url(ValueResource::getUrl('index'))];
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
            TextColumn::make('usage')->label(__('tags.usage'))->numeric()->url(fn (array $record): string => self::valuesUrl($record['tag'])),
        ])->searchable()->paginated(false)->recordActions([
            Action::make('values')->label(__('tags.values'))->url(fn (array $record): string => self::valuesUrl($record['tag'])),
            $this->tagAction('rename')->schema([
                TextInput::make('target')->label(__('tags.new_name'))->required()->maxLength(80),
                View::make('filament.pages.tag-impact'),
            ]),
            $this->tagAction('merge')->schema([
                Select::make('target')->label(__('tags.destination'))->options(fn (array $record): array => array_diff_key(Value::tagOptions(), [$record['tag'] => true]))->searchable()->required(),
                View::make('filament.pages.tag-impact'),
            ]),
            $this->tagAction('remove')->color('danger')->schema([View::make('filament.pages.tag-impact')]),
        ]);
    }

    /** @return array<string, mixed>|null */
    protected function resolveTableRecord(?string $key): ?array
    {
        $record = $this->resolveCurrentTagRecord($key);
        if ($record !== null) {
            return $record;
        }
        if ($this->tagActionSource !== null && hash('sha256', $this->tagActionSource) === $key) {
            return [ArrayRecord::getKeyName() => $key, 'tag' => $this->tagActionSource, 'usage' => 0];
        }

        return null;
    }

    private function tagAction(string $operation): Action
    {
        return Action::make($operation)->label(__('tags.'.$operation))->authorize('manage-catalog')
            ->modalHeading(fn (array $record): string => __('tags.'.$operation).' · '.$record['tag'])
            ->modalSubmitActionLabel(__('tags.apply'))
            ->modalSubmitAction(fn (Action $action): Action => $action->disabled(empty($this->tagPreview['token'])))
            ->beforeFormFilled(function (array $record): void {
                $this->tagActionSource = $record['tag'];
                $this->tagPreview = [];
            })
            ->extraModalFooterActions(fn (Action $action): array => [$action->makeModalSubmitAction('previewTags', arguments: ['preview' => true])->label(__('tags.preview'))->color('gray')])
            ->action(function (Action $action, array $record, array $data, array $arguments) use ($operation): void {
                Gate::authorize('manage-catalog');
                $service = app(ManageValueTags::class);
                $target = $data['target'] ?? null;
                if ($arguments['preview'] ?? false) {
                    $this->tagPreview = $service->preview(auth()->user(), $operation, $record['tag'], $target);
                    $action->halt();
                }
                if (empty($this->tagPreview['token'])) {
                    throw ValidationException::withMessages(['preview' => __('tags.preview_first')]);
                }
                try {
                    $service->apply(auth()->user(), $operation, $record['tag'], $target, $this->tagPreview['token']);
                } catch (ValidationException $exception) {
                    $this->tagPreview = [];
                    throw $exception;
                }
                $this->tagPreview = [];
                $this->resetTable();
                $this->dispatch('catalog-tags-updated');
                Notification::make()->title(__('tags.saved'))->success()->send();
            });
    }
}
