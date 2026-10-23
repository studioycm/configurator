<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\Actions\CreateMasterValues;
use App\Actions\CreateSharedAttributes;
use App\Filament\Forms\Components\WorkspaceTableSelect;
use App\Filament\Resources\Attributes\Schemas\AttributeBatchForm;
use App\Filament\Resources\AttributeSelectionTable;
use App\Filament\Resources\AttributeValueWorkspace;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\Value;
use App\Services\ConfiguratorInclusionDrafts;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ConfiguratorAttributeCreationForm
{
    /** @param list<string> $tagOptions */
    public static function configure(Schema $schema, Configurator $owner, array $tagOptions = []): Schema
    {
        Gate::authorize('manage-catalog');
        $shared = AttributeBatchForm::components(fn (): string => $schema->getStatePath(), $tagOptions, function () use ($schema, $tagOptions): Section {
            $workspace = AttributeValueWorkspace::make('values', fn (): string => $schema->getStatePath(), fn (Get $get): string => (string) $get('ref'), tagOptions: $tagOptions,
                extraColumns: [TableColumn::make(__('ui.fields.default'))],
                extraFields: [Actions::make([Action::make('setDefault')->iconButton()->label(fn (Get $get): string => __('configurator.creation.set_default', ['value' => $get('name')]))
                    ->icon(fn (Get $get): Heroicon => self::associationIsDefault($schema, (string) $get('ref')) ? Heroicon::Star : Heroicon::OutlinedStar)->disabled(fn (Get $get): bool => self::associationIsExcluded($schema, (string) $get('ref')))->tooltip(fn (Get $get): ?string => self::associationIsExcluded($schema, (string) $get('ref')) ? __('configurator.creation.excluded_value') : null)->authorize('manage-catalog')
                    ->action(function (Get $get) use ($schema): void {
                        Gate::authorize('manage-catalog');
                        self::syncReviews($schema);
                        $state = $schema->getRawState();
                        foreach ($state['reviews'] as &$review) {
                            foreach ($review['values'] ?? [] as $value) {
                                if (($value['option_ref'] ?? null) === $get('ref')) {
                                    $review['default_ref'] = $value['ref'];
                                }
                            }
                        }
                        unset($review);
                        $schema->rawState($state);
                    })])->key('staged-default')]);
            $workspace->afterStateUpdated(fn () => self::syncReviews($schema));

            return $workspace;
        });
        foreach ($shared as $component) {
            if ($component instanceof Repeater && $component->getName() === 'attributes') {
                $component->afterStateUpdated(fn () => self::syncReviews($schema));
            }
        }
        $pool = array_shift($shared);
        $optionNames = [];
        $masterNames = [];
        $namesLoaded = false;
        $resolveNames = function () use (&$optionNames, &$masterNames, &$namesLoaded, $schema): void {
            if ($namesLoaded) {
                return;
            }
            $state = $schema->getRawState();
            $ids = [];
            foreach ($state['reviews'] ?? [] as $review) {
                $ids = [...$ids, ...array_column($review['values'] ?? [], 'option_id')];
            }
            $attributeIds = array_column($state['reviews'] ?? [], 'attribute_id');
            foreach (Option::with('value')->where(fn ($query) => $query->whereIn('id', $ids)->orWhereIn('attribute_id', $attributeIds))->get() as $option) {
                $optionNames[$option->id] = ['name' => $option->value->labelWithSemanticId(), 'code' => $option->code, 'master' => $option->value->label, 'attribute_id' => $option->attribute_id, 'eligible' => $option->is_active && ! $option->is_hidden];
            }
            $namesLoaded = true;
            $masterNames = Value::whereIn('id', array_merge(...array_map(fn (array $row): array => array_column($row['values'] ?? [], 'value_id'), array_values($state['attributes'] ?? []))))->get()->keyBy('id')->all();
        };
        $name = function (Get $get, string $prefix = '') use ($resolveNames, &$optionNames, &$masterNames, $schema): array {
            $resolveNames();
            if ($get($prefix.'option_id')) {
                return $optionNames[$get($prefix.'option_id')] ?? ['name' => '', 'code' => '', 'master' => ''];
            }
            $state = $schema->getRawState();
            foreach ($state['attributes'] ?? [] as $attribute) {
                foreach ($attribute['values'] ?? [] as $association) {
                    if (($association['ref'] ?? null) !== $get($prefix.'option_ref')) {
                        continue;
                    }
                    $master = isset($association['value_ref']) ? collect($state['master_values'] ?? [])->firstWhere('ref', $association['value_ref']) : null;
                    $label = $master['label'] ?? ($masterNames[$association['value_id'] ?? 0]->label ?? '');

                    return ['name' => $association['name'] ?? $label, 'code' => $association['code'] ?? '', 'master' => $label];
                }
            }

            return ['name' => '', 'code' => '', 'master' => ''];
        };

        return $schema->columns(1)->components([
            View::make('filament.forms.configurator-review-errors'),
            Hidden::make('_review_stash')->default([])->dehydrated(false),
            $pool,
            Tabs::make(__('configurator.creation.sources'))->livewireProperty('catalogCreationTab')->tabs([
                'existing' => Tab::make(__('configurator.creation.existing_attributes'))->schema([
                    WorkspaceTableSelect::make('existing_attribute_ids')->label(__('configurator.inclusions.choose_shared_attributes'))->multiple()->default([])->live()->selectRowsByClick()
                        ->tableConfiguration(AttributeSelectionTable::class)->tableArguments(['owner_id' => $owner->id])
                        ->afterStateUpdated(fn () => self::syncReviews($schema)),
                ]),
                'create' => Tab::make(__('configurator.creation.create_attributes'))->schema($shared),
            ]),
            Section::make(__('configurator.creation.review'))->description(__('configurator.creation.review_help'))->headerActions([
                Action::make('reviewAttributes')->label(__('configurator.creation.refresh_review'))->authorize('manage-catalog')->action(function () use ($schema): void {
                    Gate::authorize('manage-catalog');
                    self::syncReviews($schema);
                }),
            ])->schema([
                Repeater::make('reviews')->hiddenLabel()->default([])->maxItems(500)->addable(false)->deletable(false)->reorderable(false)->collapsible()
                    ->itemLabel(fn (Schema $item): string => $item->getRawState()['_label'] ?? __('catalog.resources.attributes.singular'))
                    ->schema([
                        Hidden::make('ref')->required(), Hidden::make('attribute_id')->dehydrated(fn (mixed $state): bool => $state !== null), Hidden::make('attribute_ref')->dehydrated(fn (mixed $state): bool => $state !== null),
                        Hidden::make('_label')->dehydrated(false), Hidden::make('_excluded_association_refs')->default([])->dehydrated(false), Hidden::make('default_ref')->label(__('configurator.labels.stored_default'))->required(),
                        Section::make(__('configurator.headings.local_settings_and_values'))->compact()->columns(['default' => 1, 'md' => 2])->statePath('settings')->schema(ConfiguratorAttributeForm::settingsFields(helpTextLimit: 5000)),
                        Repeater::make('values')->hintAction(Action::make('restoreReviewValues')->label(__('configurator.creation.restore_created_values'))->authorize('manage-catalog')->visible(fn (Get $get): bool => ! empty($get('_excluded_association_refs')))->action(function (Set $set) use ($schema): void {
                            Gate::authorize('manage-catalog');
                            $set('_excluded_association_refs', []);
                            self::syncReviews($schema);
                        }))->label(__('configurator.inclusions.values.heading'))->default([])->minItems(1)->maxItems(500)->addable(fn (Get $get): bool => filled($get('attribute_id')))->addActionLabel(__('configurator.creation.restore_value'))->reorderable(false)->compact()->live()
                            ->table([TableColumn::make(__('catalog.fields.value_ui_name')), TableColumn::make(__('catalog.fields.code')), TableColumn::make(__('ui.fields.default'))])
                            ->schema([
                                Hidden::make('ref')->default(fn (): string => 'new:'.Str::uuid())->required(), Hidden::make('option_ref')->dehydrated(fn (mixed $state): bool => $state !== null),
                                Section::make()->contained(false)->compact()->schema([
                                    Select::make('option_id')->label(__('catalog.attribute_values.headings.shared'))->searchable()->live()->required(fn (Get $get): bool => filled($get('../../attribute_id')))->visible(fn (Get $get): bool => filled($get('../../attribute_id')))->dehydrated(fn (mixed $state): bool => $state !== null)
                                        ->options(function (Get $get) use ($resolveNames, &$optionNames): array {
                                            $resolveNames();

                                            return collect($optionNames)->filter(fn (array $option): bool => $option['attribute_id'] === (int) $get('../../attribute_id') && $option['eligible'])->mapWithKeys(fn (array $option, int $id): array => [$id => $option['code'].' · '.$option['name']])->all();
                                        })->afterStateUpdated(function (Get $get, Set $set) use ($name): void {
                                            $set('_name', $name($get)['name']);
                                            $set('_code', $name($get)['code']);
                                            if ($get('local_fields.subtitle_mode') === 'master') {
                                                $set('local_fields._subtitle_text', $name($get)['master']);
                                            }
                                        }),
                                    TextInput::make('_name')->label(__('catalog.fields.value_ui_name'))->formatStateUsing(fn (Get $get): string => $name($get)['name'])->readOnly()->dehydrated(false),
                                    Section::make(__('configurator.creation.local_value'))->compact()->collapsible()->collapsed()->statePath('local_fields')->schema([
                                        ...ConfiguratorValueTextFields::fields(fn (Get $get): string => $name($get, '../')['master']),
                                        TextInput::make('hint')->label(__('configurator.labels.local_hint'))->maxLength(1000),
                                        Toggle::make('hidden_by_default')->label(__('configurator.labels.initially_hidden'))->default(false),
                                        Toggle::make('disabled_by_default')->label(__('configurator.labels.initially_disabled'))->default(false),
                                    ]),
                                ]),
                                TextInput::make('_code')->label(__('catalog.fields.code'))->formatStateUsing(fn (Get $get): string => $name($get)['code'])->readOnly()->dehydrated(false),
                                Actions::make([Action::make('setDefault')->iconButton()->authorize('manage-catalog')->label(fn (Get $get): string => __('configurator.creation.set_default', ['value' => $name($get)['name']]))
                                    ->icon(fn (Get $get): Heroicon => $get('../../default_ref') === $get('ref') ? Heroicon::Star : Heroicon::OutlinedStar)
                                    ->action(function (Get $get, Set $set): void {
                                        Gate::authorize('manage-catalog');
                                        $set('../../default_ref', $get('ref'));
                                    })])->key('review-default'),
                            ])->afterStateUpdated(function (Get $get, Set $set, ?array $state): void {
                                if (! in_array($get('default_ref'), array_column($state ?? [], 'ref'), true)) {
                                    $set('default_ref', null);
                                }
                            })->deleteAction(fn (Action $action): Action => $action->requiresConfirmation()->before(function (Repeater $component, Get $get, Set $set, array $arguments): void {
                                Gate::authorize('manage-catalog');
                                $removed = $component->getRawState()[$arguments['item']] ?? [];
                                if (isset($removed['option_ref'])) {
                                    $set('_excluded_association_refs', [...($get('_excluded_association_refs') ?? []), $removed['option_ref']]);
                                }
                            })),
                    ]),
            ]),
        ]);
    }

    public static function syncReviews(Schema $schema): void
    {
        Gate::authorize('manage-catalog');
        $state = $schema->getRawState();
        $stash = $state['_review_stash'] ?? [];
        foreach ($state['reviews'] ?? [] as $key => $review) {
            $identity = isset($review['attribute_id']) ? 'id:'.$review['attribute_id'] : ($review['attribute_ref'] ?? '');
            $stash[$identity] = ['key' => $key, 'review' => $review];
        }
        $reviews = [];
        $attributes = Attribute::with('options.value')->whereIn('id', $state['existing_attribute_ids'] ?? [])->get()->keyBy('id');
        $options = $attributes->flatMap(fn (Attribute $attribute) => $attribute->options)->keyBy('id');
        $masters = Value::whereIn('id', array_merge(...array_map(fn (array $row): array => array_column($row['values'] ?? [], 'value_id'), array_values($state['attributes'] ?? []))))->get()->keyBy('id');
        $pool = array_column($state['master_values'] ?? [], null, 'ref');
        foreach ($state['existing_attribute_ids'] ?? [] as $id) {
            $identity = 'id:'.$id;
            if (isset($stash[$identity])) {
                $entry = $stash[$identity];
            } else {
                $draft = app(ConfiguratorInclusionDrafts::class)->attribute((int) $id);
                $entry = ['key' => (string) Str::uuid(), 'review' => ['ref' => $draft['id'], 'attribute_id' => (int) $id, '_label' => $attributes[$id]->label ?? '', 'settings' => ['input_type' => 'toggle', 'label_override' => null, 'help_text' => null], 'default_ref' => null,
                    'values' => collect($draft['options'])->mapWithKeys(fn (array $row): array => [(string) Str::uuid() => ['ref' => $row['id'], 'option_id' => $row['option_id'], 'local_fields' => self::localDefaults()]])->all()]];
            }
            foreach ($entry['review']['values'] as &$value) {
                $option = $options[$value['option_id'] ?? 0] ?? null;
                $value['_name'] = $option?->value->labelWithSemanticId() ?? '';
                $value['_code'] = $option?->code ?? '';
            }
            unset($value);
            $reviews[$entry['key']] = $entry['review'];
            unset($stash[$identity]);
        }
        foreach ($state['attributes'] ?? [] as $attribute) {
            if (! CreateSharedAttributes::isPopulated($attribute)) {
                continue;
            }
            $identity = $attribute['ref'];
            $entry = $stash[$identity] ?? ['key' => (string) Str::uuid(), 'review' => ['ref' => 'new:'.Str::uuid(), 'attribute_ref' => $identity, 'settings' => ['input_type' => 'toggle', 'label_override' => null, 'help_text' => null], 'default_ref' => null, 'values' => []]];
            $review = $entry['review'];
            $review['_label'] = $attribute['label'] ?? '';
            $values = [];
            foreach ($attribute['values'] ?? [] as $association) {
                if (in_array($association['ref'], $review['_excluded_association_refs'] ?? [], true)) {
                    continue;
                }
                $found = false;
                foreach ($review['values'] as $key => $value) {
                    if (($value['option_ref'] ?? null) === $association['ref']) {
                        $values[$key] = $value;
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $values[(string) Str::uuid()] = ['ref' => 'new:'.Str::uuid(), 'option_ref' => $association['ref'], 'local_fields' => self::localDefaults()];
                }
            }
            foreach ($values as &$value) {
                $association = collect($attribute['values'] ?? [])->firstWhere('ref', $value['option_ref']);
                $master = isset($association['value_ref']) ? ($pool[$association['value_ref']] ?? []) : null;
                $value['_name'] = $association['name'] ?? ($master['label'] ?? (($masters[$association['value_id'] ?? 0] ?? null)?->labelWithSemanticId() ?? ''));
                $value['_code'] = $association['code'] ?? '';
            }
            unset($value);
            $review['values'] = $values;
            if (! in_array($review['default_ref'], array_column($values, 'ref'), true)) {
                $review['default_ref'] = null;
            }
            $reviews[$entry['key']] = $review;
            unset($stash[$identity]);
        }
        $state['reviews'] = $reviews;
        $state['_review_stash'] = $stash;
        $schema->rawState($state);
        $schema->clearCachedChildSchemas();
    }

    public static function assertComposersStaged(Schema $schema): void
    {
        AttributeBatchForm::assertComposersStaged($schema);
    }

    /** @param array<string,mixed> $state */
    public static function isDirty(array $state): bool
    {
        return ! empty($state['existing_attribute_ids']) || ! empty($state['_review_stash']) || ! empty($state['reviews']) || array_any($state['master_values'] ?? [], CreateMasterValues::isPopulated(...))
            || array_any($state['attributes'] ?? [], fn (array $row): bool => CreateSharedAttributes::isPopulated($row) || array_any($row['_master_composer'] ?? [], CreateMasterValues::isPopulated(...)) || ! empty($row['_selected_value_ids']) || ! empty($row['_selected_pending']));
    }

    private static function associationIsExcluded(Schema $schema, string $ref): bool
    {
        foreach ($schema->getRawState()['reviews'] ?? [] as $review) {
            if (in_array($ref, $review['_excluded_association_refs'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    private static function associationIsDefault(Schema $schema, string $ref): bool
    {
        foreach ($schema->getRawState()['reviews'] ?? [] as $review) {
            foreach ($review['values'] ?? [] as $value) {
                if (($value['option_ref'] ?? null) === $ref && $value['ref'] === $review['default_ref']) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{label_override:null,subtitle_mode:string,display_value_override:null,hint:null,hidden_by_default:bool,disabled_by_default:bool} */
    private static function localDefaults(): array
    {
        return ['label_override' => null, 'subtitle_mode' => 'none', 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false];
    }
}
