<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Actions\CreateMasterValues;
use App\Actions\CreateSharedAttributes;
use App\Filament\Resources\AttributeValueWorkspace;
use App\Filament\Resources\SuggestedIdentifier;
use App\Models\Attribute;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttributeBatchForm
{
    /** @param Closure():string $rootStatePath
     * @param  list<string>  $tagOptions
     * @param  Closure():Section|null  $workspaceFactory
     * @return array<Component>
     */
    public static function components(Closure $rootStatePath, array $tagOptions = [], ?Closure $workspaceFactory = null): array
    {
        $fields = AttributeForm::definitionFields(function (Get $get): array {
            $ref = $get('ref');

            return array_values(array_filter(array_map(fn (array $row): ?string => ($row['ref'] ?? null) === $ref ? null : SuggestedIdentifier::reservation($row, 'key'), $get('../') ?? []), is_string(...)));
        });
        foreach ($fields as $field) {
            if ($field instanceof TextInput) {
                $field->required(fn (Get $get): bool => CreateSharedAttributes::isPopulated($get('') ?? []));
                if ($field->getName() === 'key') {
                    $field->unique(table: 'attributes', column: 'key', ignoreRecord: false);
                }
            }
        }

        return [
            self::masterPool($rootStatePath),
            Hidden::make('_active_owner')->default('')->dehydrated(false),
            Repeater::make('attributes')->label(__('catalog.resources.attributes.plural'))->model(Attribute::class)->defaultItems(1)->maxItems(500)->reorderable(false)->cloneable(false)
                ->addActionLabel(__('attribute-options.add_attribute'))->schema(Schema::make()->operation('create')->components([
                    Hidden::make('ref')->default(fn (): string => 'new:'.Str::uuid())->required(),
                    Section::make()->contained(false)->columns(['default' => 1, 'md' => 2])->schema($fields),
                    $workspaceFactory ? $workspaceFactory() : AttributeValueWorkspace::make('values', $rootStatePath, fn (Get $get): string => (string) $get('ref'), tagOptions: $tagOptions),
                ])),
        ];
    }

    /** @param Closure():string $rootStatePath */
    public static function masterPool(Closure $rootStatePath): Repeater
    {
        $users = function (Repeater $component, array $arguments) use ($rootStatePath): array {
            $ref = $component->getRawState()[$arguments['item']]['ref'] ?? null;
            $root = data_get($component->getLivewire(), $rootStatePath()) ?? [];
            $names = [];
            foreach ($root['attributes'] ?? [['label' => $root['label'] ?? __('catalog.resources.attributes.singular'), 'values' => $root['pending_options'] ?? $root['new_values'] ?? []]] as $row) {
                if (in_array($ref, array_column($row['values'] ?? [], 'value_ref'), true)) {
                    $names[] = $row['label'] ?: __('catalog.resources.attributes.singular');
                }
            }

            return $names;
        };

        return Repeater::make('master_values')->visible(fn (mixed $state): bool => ! empty($state))->label(__('attribute-options.new_in_batch'))->default([])->maxItems(500)->addable(false)->reorderable(false)->compact()
            ->helperText(__('attribute-options.unreferenced'))
            ->schema([Hidden::make('ref')->required(), Hidden::make('tags'), Hidden::make('description'), TextInput::make('label')->label(__('catalog.fields.ui_name'))->readOnly(), TextInput::make('semantic_id')->label(__('catalog.fields.semantic_id'))->readOnly()])
            ->columns(['default' => 1, 'md' => 2])
            ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation()->disabled(fn (Repeater $component, array $arguments): bool => $users($component, $arguments) !== [])->tooltip(fn (Repeater $component, array $arguments): ?string => ($names = $users($component, $arguments)) ? __('attribute-options.used_by', ['names' => implode(', ', $names)]) : null));
    }

    public static function assertComposersStaged(Schema $schema): void
    {
        foreach ($schema->getRawState()['attributes'] ?? [] as $key => $row) {
            if (array_any($row['_master_composer'] ?? [], CreateMasterValues::isPopulated(...)) || ! empty($row['_selected_value_ids']) || ! empty($row['_selected_pending'])) {
                throw ValidationException::withMessages([$schema->getStatePath().'.attributes.'.$key.'._master_composer' => __('attribute-options.unstaged')]);
            }
        }
    }
}
