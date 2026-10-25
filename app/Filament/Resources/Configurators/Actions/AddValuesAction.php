<?php

namespace App\Filament\Resources\Configurators\Actions;

use App\Actions\AddConfiguratorValues;
use App\Actions\CreateMasterValues;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Attributes\Schemas\AttributeBatchForm;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Filament\Resources\AttributeValueWorkspace;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\OptionSelectionTable;
use App\Models\ConfiguratorAttribute;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Component;

class AddValuesAction
{
    public static function make(ConfiguratorAttribute $owner): Action
    {
        return Action::make('includeMany')->label(__('configurator.inclusions.values.actions.include_many'))->authorize('manage-catalog')->slideOver()->stickyModalFooter()->databaseTransaction(false)
            ->modalHeading(__('configurator.inclusions.values.include_heading', ['attribute' => $owner->label_override ?? $owner->attribute->label]))
            ->modalSubmitActionLabel(__('configurator.creation.save_and_include'))->modalCancelAction(false)->modalCloseButton(false)->closeModalByClickingAway(false)->closeModalByEscaping(false)
            ->beforeFormFilled(function (Component $livewire): void {
                Gate::authorize('manage-catalog');
                $livewire->catalogCreationTab = 'existing';
            })
            ->beforeFormValidated(function (Action $action): void {
                Gate::authorize('manage-catalog');
                AttributeForm::assertValuesStaged(self::schema($action));
            })
            ->schema(function (Schema $schema) use ($owner): Schema {
                Gate::authorize('manage-catalog');
                $root = fn (): string => $schema->getStatePath();

                return $schema->columns(1)->components([Group::make([
                    Hidden::make('_active_owner')->default('')->dehydrated(false),
                    AttributeBatchForm::masterPool($root),
                    Tabs::make(__('configurator.creation.sources'))->livewireProperty('catalogCreationTab')->tabs([
                        'existing' => Tab::make(__('configurator.creation.existing_values'))->schema([
                            WorkspaceTableSelect::make('existing_option_ids')->label(__('catalog.attribute_values.headings.shared_plural'))->multiple()->default([])->live()->selectRowsByClick()->tableConfiguration(OptionSelectionTable::class)->tableArguments(['owner_id' => $owner->id]),
                        ]),
                        'create' => Tab::make(__('configurator.creation.new_values'))->schema([
                            AttributeValueWorkspace::make('new_values', $root, fn (Get $get): string => 'inclusion:'.$owner->id, $owner->attribute, Value::tagVocabulary())->description(__('configurator.creation.shared_values_help')),
                        ]),
                    ]),
                ])->columnSpanFull()->extraAttributes(fn (): array => ['data-catalog-draft-guard' => true, 'x-data' => 'configuratorCreationGuard('.Js::from(['path' => $schema->getStatePath(), 'kind' => 'values', 'discardMessage' => __('catalog.creation.discard_heading')]).')'])]);
            })
            ->extraModalFooterActions(fn (Action $action): array => [Action::make('discardDraft')->label(__('catalog.creation.cancel'))->color('gray')
                ->requiresConfirmation(fn (): bool => self::isDirty(self::schema($action)->getRawState()))->modalHidden(fn (): bool => ! self::isDirty(self::schema($action)->getRawState()))
                ->modalHeading(__('catalog.creation.discard_heading'))->modalSubmitActionLabel(__('catalog.creation.discard'))->modalCancelActionLabel(__('catalog.creation.keep_editing'))->cancelParentActions('includeMany')->action(fn () => null)])
            ->action(function (array $data, Action $action, AddConfiguratorValues $addConfiguratorValues, Component $livewire) use ($owner): void {
                $payload = ['existing_option_ids' => array_values($data['existing_option_ids'] ?? []),
                    'master_values' => array_values(array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'label', 'semantic_id', 'tags', 'description'])), $data['master_values'] ?? [])),
                    'new_values' => array_values(array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'value_id', 'value_ref', 'code'])), $data['new_values'] ?? []))];
                ConfiguratorFormErrors::run(fn () => $addConfiguratorValues->handle(auth()->user(), $owner->configurator, $owner->id, $payload), self::schema($action));
                $livewire->creationSaved();
            });
    }

    private static function schema(Action $action): Schema
    {
        return $action->getLivewire()->getSchema('mountedActionSchema'.$action->getNestingIndex());
    }

    /** @param array<string,mixed> $state */
    private static function isDirty(array $state): bool
    {
        return ! empty($state['existing_option_ids']) || ! empty($state['new_values']) || array_any($state['master_values'] ?? [], CreateMasterValues::isPopulated(...)) || array_any($state['_master_composer'] ?? [], CreateMasterValues::isPopulated(...)) || ! empty($state['_selected_value_ids']) || ! empty($state['_selected_pending']);
    }
}
