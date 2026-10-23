<?php

namespace App\Filament\Resources\Configurators\Actions;

use App\Actions\AddConfiguratorAttributes;
use App\ConfigInputType;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorAttributeCreationForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Configurator;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Component;

class AddAttributesAction
{
    public static function make(Configurator $owner): Action
    {
        return Action::make('includeMany')->label(__('configurator.labels.include_attributes'))->authorize('manage-catalog')->slideOver()->stickyModalFooter()->databaseTransaction(false)
            ->modalSubmitActionLabel(__('configurator.creation.save_and_add'))->modalCancelAction(false)->modalCloseButton(false)->closeModalByClickingAway(false)->closeModalByEscaping(false)
            ->beforeFormFilled(function (Component $livewire): void {
                Gate::authorize('manage-catalog');
                $livewire->catalogCreationTab = 'existing';
            })
            ->beforeFormValidated(function (Action $action): void {
                Gate::authorize('manage-catalog');
                ConfiguratorAttributeCreationForm::syncReviews(self::schema($action));
                ConfiguratorAttributeCreationForm::assertComposersStaged(self::schema($action));
            })
            ->schema(function (Schema $schema) use ($owner): Schema {
                Gate::authorize('manage-catalog');
                ConfiguratorAttributeCreationForm::configure($schema, $owner, array_values(Value::tagOptions()));
                $components = $schema->getComponents();

                return $schema->components([Group::make($components)->columnSpanFull()->extraAttributes(fn (): array => ['data-catalog-draft-guard' => true, 'x-data' => 'configuratorCreationGuard('.Js::from(['path' => $schema->getStatePath(), 'kind' => 'attributes', 'discardMessage' => __('catalog.creation.discard_heading')]).')'])]);
            })
            ->extraModalFooterActions(fn (Action $action): array => [Action::make('discardDraft')->label(__('catalog.creation.cancel'))->color('gray')
                ->requiresConfirmation(fn (): bool => ConfiguratorAttributeCreationForm::isDirty(self::schema($action)->getRawState()))
                ->modalHidden(fn (): bool => ! ConfiguratorAttributeCreationForm::isDirty(self::schema($action)->getRawState()))
                ->modalHeading(__('catalog.creation.discard_heading'))->modalSubmitActionLabel(__('catalog.creation.discard'))->modalCancelActionLabel(__('catalog.creation.keep_editing'))->cancelParentActions('includeMany')->action(fn () => null)])
            ->action(function (array $data, Action $action, AddConfiguratorAttributes $addConfiguratorAttributes, Component $livewire) use ($owner): void {
                $payload = [
                    'existing_attribute_ids' => array_values($data['existing_attribute_ids'] ?? []),
                    'master_values' => array_values(array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'label', 'semantic_id', 'tags', 'description'])), $data['master_values'] ?? [])),
                    'attributes' => array_values(array_map(function (array $row): array {
                        $projected = array_intersect_key($row, array_flip(['ref', 'label', 'key', 'values']));
                        $projected['values'] = array_values(array_map(fn (array $value): array => array_intersect_key($value, array_flip(['ref', 'value_id', 'value_ref', 'code'])), $row['values'] ?? []));

                        return $projected;
                    }, $data['attributes'] ?? [])),
                    'reviews' => array_values(array_map(function (array $row): array {
                        $projected = array_intersect_key($row, array_flip(['ref', 'attribute_id', 'attribute_ref', 'settings', 'values', 'default_ref']));
                        if (($projected['settings']['input_type'] ?? null) instanceof ConfigInputType) {
                            $projected['settings']['input_type'] = $projected['settings']['input_type']->value;
                        }
                        $projected['values'] = array_values(array_map(function (array $value): array {
                            $projected = array_intersect_key($value, array_flip(['ref', 'option_id', 'option_ref', 'local_fields']));
                            if (isset($projected['local_fields'])) {
                                $projected['local_fields'] = array_intersect_key($projected['local_fields'], array_flip(['label_override', 'subtitle_mode', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default']));
                            }

                            return $projected;
                        }, $row['values'] ?? []));

                        return $projected;
                    }, $data['reviews'] ?? [])),
                ];
                ConfiguratorFormErrors::run(fn () => $addConfiguratorAttributes->handle(auth()->user(), $owner, $payload), self::schema($action));
                $livewire->creationSaved();
            });
    }

    private static function schema(Action $action): Schema
    {
        return $action->getLivewire()->getSchema('mountedActionSchema'.$action->getNestingIndex());
    }
}
