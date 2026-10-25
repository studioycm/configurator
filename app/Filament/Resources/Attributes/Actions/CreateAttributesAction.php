<?php

namespace App\Filament\Resources\Attributes\Actions;

use App\Actions\CreateMasterValues;
use App\Actions\CreateSharedAttributes;
use App\Filament\Resources\Attributes\Schemas\AttributeBatchForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Component;

class CreateAttributesAction
{
    public static function make(): Action
    {
        return Action::make('createMany')->label(__('attribute-options.create_attributes'))->authorize('manage-catalog')->slideOver()->stickyModalFooter()->databaseTransaction(false)
            ->modalSubmitActionLabel(__('catalog.creation.create'))->modalCancelAction(false)->modalCloseButton(false)->closeModalByClickingAway(false)->closeModalByEscaping(false)
            ->beforeFormValidated(function (Action $action): void {
                Gate::authorize('manage-catalog');
                AttributeBatchForm::assertComposersStaged(self::schema($action));
            })
            ->schema(function (Schema $schema): Schema {
                Gate::authorize('manage-catalog');

                return $schema->columns(1)->components([
                    Group::make(AttributeBatchForm::components(fn (): string => $schema->getStatePath(), Value::tagVocabulary()))->columnSpanFull()
                        ->extraAttributes(fn (): array => ['data-catalog-draft-guard' => true, 'x-data' => 'attributeCreationGuard('.Js::from(['path' => $schema->getStatePath(), 'discardMessage' => __('catalog.creation.discard_heading')]).')']),
                ]);
            })
            ->extraModalFooterActions(fn (Action $action): array => [Action::make('discardDraft')->label(__('catalog.creation.cancel'))->color('gray')
                ->requiresConfirmation(fn (): bool => self::isDirty(self::schema($action)->getRawState()))
                ->modalHidden(fn (): bool => ! self::isDirty(self::schema($action)->getRawState()))
                ->modalHeading(__('catalog.creation.discard_heading'))->modalSubmitActionLabel(__('catalog.creation.discard'))->modalCancelActionLabel(__('catalog.creation.keep_editing'))
                ->cancelParentActions('createMany')->action(fn () => null)])
            ->action(function (array $data, Action $action, CreateSharedAttributes $createSharedAttributes, Component $livewire): void {
                $attributes = array_map(function (array $row): array {
                    $row = array_intersect_key($row, array_flip(['ref', 'label', 'key', 'values']));
                    $row['values'] = array_map(fn (array $value): array => array_intersect_key($value, array_flip(['ref', 'value_id', 'value_ref', 'code'])), $row['values'] ?? []);

                    return $row;
                }, $data['attributes'] ?? []);
                $pool = array_values(array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'label', 'semantic_id', 'tags', 'description'])), $data['master_values'] ?? []));
                $created = ConfiguratorFormErrors::run(fn (): array => $createSharedAttributes->handle(auth()->user(), $attributes, $pool), self::schema($action));
                $livewire->dispatch('catalog-record-saved');
                Notification::make()->success()->title(trans_choice('attribute-options.created', count($created), ['count' => count($created)]))->send();
            });
    }

    private static function schema(Action $action): Schema
    {
        return $action->getLivewire()->getSchema('mountedActionSchema'.$action->getNestingIndex());
    }

    /** @param array<string,mixed> $state */
    private static function isDirty(array $state): bool
    {
        if (array_any($state['master_values'] ?? [], CreateMasterValues::isPopulated(...))) {
            return true;
        }
        foreach ($state['attributes'] ?? [] as $row) {
            if (CreateSharedAttributes::isPopulated($row) || array_any($row['_master_composer'] ?? [], CreateMasterValues::isPopulated(...)) || ! empty($row['_selected_value_ids']) || ! empty($row['_selected_pending'])) {
                return true;
            }
        }

        return false;
    }
}
