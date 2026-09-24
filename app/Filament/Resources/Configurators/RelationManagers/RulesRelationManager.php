<?php

namespace App\Filament\Resources\Configurators\RelationManagers;

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorRuleForm;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Configurator && Gate::allows('manage-catalog');
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['driverAttribute.attribute', 'targetAttribute.attribute', 'effects.targetAttribute.attribute'])->withCount('mappingSets'))
            ->columns([
                TextColumn::make('label')->searchable(fn (): bool => ! $this->isTableReordering), TextColumn::make('kind')->badge(),
                TextColumn::make('summary')->state(fn (ConfiguratorRule $record): string => $record->kind === 'Mapping'
                    ? ($record->driverAttribute?->label_override ?? $record->driverAttribute?->attribute->label).' → '.($record->targetAttribute?->label_override ?? $record->targetAttribute?->attribute->label).' · '.$record->mapping_sets_count.' sets'
                    : $record->effects->take(5)->map(fn ($effect): string => Str::headline($effect->kind).' · '.($effect->targetAttribute?->label_override ?? $effect->targetAttribute?->attribute->label))->implode('; '))->wrap(),
                IconColumn::make('is_active')->label('Enabled')->boolean(),
            ])->defaultSort('priority', 'desc')->reorderable('priority', direction: 'desc')->paginated(false)
            ->headerActions([$this->createAction('Mapping'), $this->createAction('Advanced')])
            ->recordActions([
                Action::make('edit')->label('Edit')->authorize('manage-catalog')
                    ->schema(fn (ConfiguratorRule $record): array => ConfiguratorRuleForm::components($this->owner(), $record->kind))
                    ->fillForm(fn (ConfiguratorRule $record): array => $this->ruleDraft($record->id))
                    ->action(fn (ConfiguratorRule $record) => $this->saveRule($record->id, $record->kind, $this->rawDraft())),
                Action::make('moveUp')->label('Move up')->authorize('manage-catalog')->action(fn (ConfiguratorRule $record) => $this->moveRule($record->id, -1)),
                Action::make('moveDown')->label('Move down')->authorize('manage-catalog')->action(fn (ConfiguratorRule $record) => $this->moveRule($record->id, 1)),
                Action::make('remove')->label('Remove')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                    ->schema([View::make('filament.forms.validation-summary')])
                    ->modalDescription('Remove this rule and its owned conditions, effects and mapping sets in one save. Other rules and shared definitions remain available.')
                    ->action(fn (ConfiguratorRule $record) => $this->removeRule($record->id)),
            ]);
    }

    private function createAction(string $kind): Action
    {
        return Action::make('add'.$kind)->label($kind === 'Mapping' ? 'Add mapping' : 'Add advanced rule')->authorize('manage-catalog')
            ->schema(fn (): array => ConfiguratorRuleForm::components($this->owner(), $kind))
            ->fillForm(fn (): array => app(ConfiguratorRuleDraft::class)->fromRule(['id' => 'new:'.Str::uuid(), 'label' => '', 'kind' => $kind, 'is_active' => true, 'priority' => 0, 'driver_configurator_attribute_id' => null, 'target_configurator_attribute_id' => null, 'conditions' => [], 'effects' => [], 'sets' => []]))
            ->action(fn () => $this->saveRule(null, $kind, $this->rawDraft()));
    }

    /** @param array<string, mixed> $data */
    public function saveRule(?int $id, string $kind, array $data): void
    {
        $owner = $this->owner();
        try {
            app(SaveConfiguratorDefinition::class)->change(auth()->user(), $owner, function (array $draft) use ($id, $kind, $data): array {
                $index = array_search((string) $id, array_column($draft['rules'], 'id'), true);
                if ($id !== null && $index === false) {
                    throw ValidationException::withMessages(['rule' => 'This rule does not belong to this Configurator.']);
                }
                $expected = $id === null ? $kind : $draft['rules'][$index]['kind'];
                if (! in_array($expected, ['Mapping', 'Advanced'], true) || ($data['kind'] ?? null) !== $expected || $kind !== $expected) {
                    throw ValidationException::withMessages(['kind' => 'Rule kind is fixed. Create a separate rule to change kind.']);
                }
                $rule = app(ConfiguratorRuleDraft::class)->toRule($data);
                if ($id !== null) {
                    $rule['id'] = (string) $id;
                    $rule['priority'] = $draft['rules'][$index]['priority'];
                    $draft['rules'][$index] = $rule;
                } else {
                    if (! str_starts_with((string) $rule['id'], 'new:')) {
                        throw ValidationException::withMessages(['id' => 'New rules require a new staging key.']);
                    }
                    $rule['priority'] = $draft['rules'] === [] ? 0 : max(array_column($draft['rules'], 'priority')) + 1;
                    $draft['rules'][] = $rule;
                }

                return $draft;
            });
        } catch (\Throwable $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->getMountedActionSchema(), '/^rules\.\d+\./');
        }
        $this->saved();
    }

    public function removeRule(int $id): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($id): array {
            if (! in_array((string) $id, array_column($draft['rules'], 'id'), true)) {
                throw ValidationException::withMessages(['rule' => 'This rule does not belong to this Configurator.']);
            }
            $draft['rules'] = array_values(array_filter($draft['rules'], fn (array $rule): bool => (string) $rule['id'] !== (string) $id));

            return $draft;
        }), $this->getMountedActionSchema());
        $this->saved();
    }

    public function toggleTableReordering(): void
    {
        Gate::authorize('manage-catalog');
        $this->tableSearch = '';
        $this->tableColumnSearches = [];
        $this->isTableReordering = ! $this->isTableReordering;
        $this->resetTable();
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->reorder(auth()->user(), $this->owner(), 'rules', $order), $this->getMountedActionSchema());
        $this->saved();
    }

    public function moveRule(int $id, int $direction): void
    {
        ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->change(auth()->user(), $this->owner(), function (array $draft) use ($id, $direction): array {
            if (! in_array($direction, [-1, 1], true)) {
                throw ValidationException::withMessages(['order' => 'Choose move up or move down.']);
            }
            usort($draft['rules'], fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
            $index = array_search((string) $id, array_column($draft['rules'], 'id'), true);
            if ($index === false) {
                throw ValidationException::withMessages(['order' => 'This rule does not belong to this Configurator.']);
            }
            $next = $index + $direction;
            if (isset($draft['rules'][$next])) {
                [$draft['rules'][$index], $draft['rules'][$next]] = [$draft['rules'][$next], $draft['rules'][$index]];
            }
            foreach ($draft['rules'] as $rank => &$rule) {
                $rule['priority'] = count($draft['rules']) - 1 - $rank;
            }

            return $draft;
        }), $this->getMountedActionSchema());
        $this->saved();
    }

    /** @return array<string, mixed> */
    private function ruleDraft(int $id): array
    {
        foreach (app(ConfiguratorDefinitionLoader::class)->draft($this->owner())['rules'] as $rule) {
            if ((string) $rule['id'] === (string) $id) {
                return app(ConfiguratorRuleDraft::class)->fromRule($rule);
            }
        }
        abort(404);
    }

    /** @return array<string, mixed> */
    private function rawDraft(): array
    {
        $state = $this->getMountedActionSchema()->getRawState();

        return $state instanceof Arrayable ? $state->toArray() : $state;
    }

    private function owner(): Configurator
    {
        Gate::authorize('manage-catalog');

        return Configurator::findOrFail($this->getOwnerRecord()->getKey());
    }

    private function saved(): void
    {
        $this->resetTable();
        $this->dispatch('configurator-updated');
        Notification::make()->title('Rules saved')->success()->send();
    }
}
