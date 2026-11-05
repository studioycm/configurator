<?php

namespace App\Filament\Resources\TourDefinitions;

use App\Filament\Resources\TourDefinitions\Pages\ManageTourDefinitions;
use App\Models\TourDefinition;
use App\Services\GuidedTours;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TourDefinitionResource extends Resource
{
    protected static ?string $model = TourDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('tours.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tours.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('tours.navigation');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-catalog') && DatabaseSchema::hasTable('tour_definitions');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('stage')->label(__('tours.stage'))->options(GuidedTours::pages())->default('images')->required()->live()->selectablePlaceholder(false)->columnSpanFull()->helperText(__('tours.stage_help')),
            TextInput::make('title')->label(__('tours.title'))->required()->maxLength(255),
            TextInput::make('key')->label(__('tours.key'))->required()->alphaDash()->maxLength(100),
            TextInput::make('locale')->label(__('tours.locale'))->default(config('app.locale'))->required()->maxLength(10),
            Toggle::make('enabled')->label(__('tours.enabled'))->default(false),
            Repeater::make('steps')->label(__('tours.steps'))->helperText(__('tours.steps_help'))->columnSpanFull()
                ->required()->minItems(1)->maxItems(30)->defaultItems(1)->reorderableWithButtons()->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->columns(2)->schema([
                    TextInput::make('key')->label(__('tours.step_key'))->default(fn (): string => (string) Str::uuid())->required()->alphaDash()->maxLength(100),
                    Select::make('target')->label(__('tours.target'))->options(fn (Get $get): array => GuidedTours::targets($get('../../stage') ?? 'images'))->required()->selectablePlaceholder(false),
                    Select::make('placement')->label(__('tours.placement'))->options(GuidedTours::placements())->default('bottom')->required()->selectablePlaceholder(false),
                    TextInput::make('title')->label(__('tours.title'))->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('description')->label(__('tours.description'))->required()->maxLength(2000)->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('title')->defaultSort('title')->columns([
            TextColumn::make('title')->label(__('tours.title'))->searchable()->sortable(),
            TextColumn::make('stage')->label(__('tours.stage'))->formatStateUsing(fn (?string $state): string => GuidedTours::pages()[$state] ?? $state ?? ''),
            TextColumn::make('steps_count')->label(__('tours.steps'))->state(fn (TourDefinition $record): int => count($record->steps)),
            TextColumn::make('key')->label(__('tours.key'))->searchable(),
            TextColumn::make('locale')->label(__('tours.locale')),
            IconColumn::make('enabled')->label(__('tours.enabled'))->boolean(),
            TextColumn::make('revision')->label(__('tours.revision')),
        ])->filters([
            SelectFilter::make('stage')->label(__('tours.stage'))->options(GuidedTours::pages())
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $value): Builder => $query->where('steps->[0]->page', $value))),
        ])->recordActions([
            EditAction::make()->slideOver()->mutateRecordDataUsing(fn (TourDefinition $record, array $data): array => [...$data, 'stage' => $record->stage])
                ->using(fn (Action $action, TourDefinition $record, array $data): TourDefinition => static::saveAction($action, $data, $record)),
            DeleteAction::make(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function saveAction(Action $action, array $data, ?TourDefinition $record = null): TourDefinition
    {
        try {
            return app(GuidedTours::class)->save(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            $livewire = $action->getLivewire();
            $schema = $livewire->getSchema($livewire->getMountedActionSchemaName());
            $stepKeys = array_keys($schema->getRawState()['steps'] ?? []);
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $field = preg_replace_callback('/^steps\.(\d+)(\.|$)/', fn (array $match): string => 'steps.'.($stepKeys[(int) $match[1]] ?? $match[1]).$match[2], $field);
                $errors[$schema->getStatePath().'.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageTourDefinitions::route('/')];
    }
}
