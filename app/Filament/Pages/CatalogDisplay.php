<?php

namespace App\Filament\Pages;

use App\Actions\SaveCatalogPresentationSettings;
use App\Actions\SaveGroupSettings;
use App\Actions\SaveGroupTags;
use App\Filament\Resources\CatalogDisplayForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Services\CatalogPresentation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CatalogDisplay extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected string $view = 'filament.pages.catalog-display';

    public array $data = [];

    public array $groupData = [];

    public array $tagData = [];

    #[Locked]
    public string $displayVersion = 'defaults:v1';

    #[Locked]
    public ?int $selectedGroupId = null;

    #[Locked]
    public ?int $pendingGroupId = null;

    #[Locked]
    public bool $groupIsLeaf = false;

    #[Locked]
    public string $presentationFingerprint = '';

    #[Locked]
    public string $tagFingerprint = '';

    #[Locked]
    public array $savedGroupData = [];

    #[Locked]
    public array $savedTagData = [];

    #[Locked]
    public array $savedSharedData = [];

    #[Locked]
    public ?array $recovery = null;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public function getTitle(): string
    {
        return __('catalog.display.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.display.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public function mount(): void
    {
        Gate::authorize('manage-catalog');
        $current = app(CatalogPresentation::class)->current();
        $this->displayVersion = $current['version'];
        $this->recovery = $current['recovery'];
        $this->form->fill($current['settings']);
        $this->savedSharedData = $this->data;
        $this->groupForm->fill();
        $this->tagForm->fill(['value_ids' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(1)->components(CatalogDisplayForm::shared());
    }

    public function groupForm(Schema $schema): Schema
    {
        return $schema->statePath('groupData')->columns(1)->components(CatalogDisplayForm::group());
    }

    public function tagForm(Schema $schema): Schema
    {
        return $schema->statePath('tagData')->columns(1)->components(CatalogDisplayForm::tags());
    }

    /** @return array{shared:bool,group:bool,tags:bool} */
    public function draftStatuses(): array
    {
        return [
            'shared' => $this->data !== $this->savedSharedData,
            'group' => $this->selectedGroupId !== null && $this->presentationDirty(),
            'tags' => $this->selectedGroupId !== null && $this->tagData !== $this->savedTagData,
        ];
    }

    public function categoryAction(): Action
    {
        return $this->vocabularyAction(false);
    }

    public function valueAction(): Action
    {
        return $this->vocabularyAction(true);
    }

    public function removeCategoryAction(): Action
    {
        return $this->removeVocabularyAction(false);
    }

    public function removeValueAction(): Action
    {
        return $this->removeVocabularyAction(true);
    }

    private function vocabularyAction(bool $value): Action
    {
        return Action::make($value ? 'value' : 'category')->label(__('catalog.display.'.($value ? 'edit_values' : 'edit_categories')))
            ->visible(fn (): bool => Gate::allows('manage-catalog'))->schema(CatalogDisplayForm::vocabulary($value))
            ->action(function (array $data) use ($value): void {
                Gate::authorize('manage-catalog');
                $id = $data['record_id'] ?? null;
                unset($data['record_id']);
                $record = $id === null ? null : ($value ? GroupTagValue::query() : GroupTagCategory::query())->findOrFail($id);
                $writer = app(SaveGroupTags::class);
                ConfiguratorFormErrors::run(fn () => $value
                    ? $writer->saveValue(auth()->user(), $record, $data)
                    : $writer->saveCategory(auth()->user(), $record, $data), $this->getMountedActionSchema(), '/^data\./');
                DB::afterCommit(fn () => $this->notifySaved());
            });
    }

    private function removeVocabularyAction(bool $value): Action
    {
        return Action::make($value ? 'removeValue' : 'removeCategory')->label(__('catalog.display.'.($value ? 'remove_value' : 'remove_category')))
            ->color('danger')->visible(fn (): bool => Gate::allows('manage-catalog'))->requiresConfirmation()
            ->modalDescription(__('catalog.display.removal_hint'))->schema([CatalogDisplayForm::vocabularyRecord($value)->required()])
            ->action(function (array $data) use ($value): void {
                Gate::authorize('manage-catalog');
                $writer = app(SaveGroupTags::class);
                ConfiguratorFormErrors::run(function () use ($value, $writer, $data): void {
                    try {
                        if ($value) {
                            $writer->removeValue(auth()->user(), GroupTagValue::query()->findOrFail($data['record_id']));
                        } else {
                            $writer->removeCategory(auth()->user(), GroupTagCategory::query()->findOrFail($data['record_id']));
                        }
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages(['record_id' => array_merge(...array_values($exception->errors()))]);
                    }
                }, $this->getMountedActionSchema());
                DB::afterCommit(fn () => $this->notifySaved());
            });
    }

    public function resetDefaults(): void
    {
        Gate::authorize('manage-catalog');
        $this->form->fill(app(CatalogPresentation::class)->defaults());
    }

    public function resetGroupOverrides(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectedGroup();
        foreach (app(CatalogPresentation::class)->overrideKeys() as $key) {
            $this->groupData['use_'.$key] = false;
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-catalog');
        $draft = $this->data;
        try {
            $saved = app(SaveCatalogPresentationSettings::class)->handle(auth()->user(), $this->form->getState(), $this->displayVersion);
        } catch (ValidationException $exception) {
            $this->rethrowFormErrors($exception, '/^settings/', 'data');
        }
        DB::afterCommit(function () use ($draft, $saved): void {
            if ($this->data !== $draft) {
                return;
            }
            $this->displayVersion = $saved->version;
            $this->recovery = null;
            $this->form->fill($saved->settings);
            $this->savedSharedData = $this->data;
            $this->notifySaved();
        });
    }

    public function requestGroup(mixed $id): void
    {
        Gate::authorize('manage-catalog');
        if ((! is_int($id) && ! is_string($id)) || preg_match('/^[1-9][0-9]*$/D', (string) $id) !== 1 || (string) (int) $id !== (string) $id) {
            throw ValidationException::withMessages(['groupData' => __('catalog.display.invalid_group')]);
        }
        $next = Group::query()->find((int) $id);
        if ($next === null) {
            throw ValidationException::withMessages(['groupData' => __('catalog.display.invalid_group')]);
        }
        if ($this->selectedGroupId === (int) $next->id) {
            return;
        }
        if ($this->selectedGroupId !== null && $this->groupDraftsDirty()) {
            $this->pendingGroupId = (int) $next->id;
            $this->groupData['requested_group_id'] = $this->selectedGroupId;

            return;
        }
        $this->loadGroup((int) $next->id);
    }

    public function resolveGroupChange(string $choice): void
    {
        Gate::authorize('manage-catalog');
        if (! in_array($choice, ['save', 'discard', 'stay'], true)) {
            throw ValidationException::withMessages(['groupData' => __('catalog.display.invalid_choice')]);
        }
        $destination = $this->pendingGroupId;
        if ($choice === 'stay' || $destination === null) {
            $this->pendingGroupId = null;

            return;
        }
        if ($choice === 'save') {
            if ($this->presentationDirty()) {
                $this->saveGroupPresentation();
            }
            if ($this->tagData !== $this->savedTagData) {
                $this->saveGroupTags();
            }
            DB::afterCommit(function () use ($destination): void {
                if ($this->pendingGroupId === $destination && ! $this->groupDraftsDirty()) {
                    $this->loadGroup($destination);
                }
            });

            return;
        }
        $this->loadGroup($destination);
    }

    public function saveGroupPresentation(): void
    {
        Gate::authorize('manage-catalog');
        $group = $this->selectedGroup();
        $draft = $this->groupData;
        $state = $this->groupForm->getState();
        $own = [];
        foreach (app(CatalogPresentation::class)->overrideKeys() as $key) {
            if (($state['use_'.$key] ?? false) === true) {
                $own[$key] = $state[$key] ?? null;
            }
        }
        try {
            app(SaveGroupSettings::class)->patchPresentation(auth()->user(), $group,
                ['catalog_presentation' => $own],
                array_values(array_diff(app(CatalogPresentation::class)->overrideKeys(), array_keys($own))),
                $this->presentationFingerprint,
            );
        } catch (ValidationException $exception) {
            $this->rethrowFormErrors($exception, '/^settings\.result_settings\.catalog_presentation/', 'groupData');
        }
        DB::afterCommit(function () use ($group, $draft): void {
            if ($this->selectedGroupId === (int) $group->id && $this->groupData === $draft) {
                DB::transaction(fn () => $this->fillGroupPresentation($group->fresh()));
                $this->notifySaved();
            }
        });
    }

    public function saveGroupTags(): void
    {
        Gate::authorize('manage-catalog');
        $group = $this->selectedGroup();
        $draft = $this->tagData;
        $state = $this->tagForm->getState();
        try {
            app(SaveGroupTags::class)->assign(auth()->user(), $group, $state['value_ids'] ?? [], $this->tagFingerprint);
        } catch (ValidationException $exception) {
            $this->rethrowFormErrors($exception, '/^tag_values/', 'tagData.value_ids');
        }
        DB::afterCommit(function () use ($group, $draft): void {
            if ($this->selectedGroupId === (int) $group->id && $this->tagData === $draft) {
                $this->tagFingerprint = app(SaveGroupTags::class)->fingerprint($group);
                $this->savedTagData = $this->tagData;
                $this->notifySaved();
            }
        });
    }

    private function selectedGroup(): Group
    {
        Gate::authorize('manage-catalog');
        if ($this->selectedGroupId === null) {
            throw ValidationException::withMessages(['groupData' => __('catalog.display.invalid_group')]);
        }

        return Group::query()->findOrFail($this->selectedGroupId);
    }

    private function loadGroup(int $id): void
    {
        Gate::authorize('manage-catalog');
        DB::transaction(function () use ($id): void {
            $group = Group::query()->findOrFail($id);
            $this->selectedGroupId = (int) $group->id;
            $this->pendingGroupId = null;
            $this->fillGroupPresentation($group);
            $this->tagFingerprint = app(SaveGroupTags::class)->fingerprint($group);
            $this->tagForm->fill(['value_ids' => $group->tagValues()->pluck('group_tag_values.id')->map(fn (mixed $value): int => (int) $value)->all()]);
            $this->savedTagData = $this->tagData;
        });
    }

    private function fillGroupPresentation(Group $group): void
    {
        $service = app(CatalogPresentation::class);
        $shared = $service->current()['settings'];
        $available = $service->resolveCategoryIds($shared, [$group]);
        $effective = $service->effective($group, $shared, $available);
        $own = $group->result_settings['catalog_presentation'] ?? [];
        $state = ['requested_group_id' => $group->id];
        foreach ($service->overrideKeys() as $key) {
            $state['use_'.$key] = is_array($own) && array_key_exists($key, $own);
            $state[$key] = $effective[$key];
        }
        $this->groupIsLeaf = ! $group->children()->exists();
        $this->presentationFingerprint = $service->groupFingerprint($group);
        $this->groupForm->fill($state);
        $this->savedGroupData = $this->groupData;
    }

    private function groupDraftsDirty(): bool
    {
        return $this->presentationDirty() || $this->tagData !== $this->savedTagData;
    }

    private function presentationDirty(): bool
    {
        return array_diff_key($this->groupData, ['requested_group_id' => true]) !== array_diff_key($this->savedGroupData, ['requested_group_id' => true]);
    }

    private function rethrowFormErrors(ValidationException $exception, string $pattern, string $prefix): never
    {
        $errors = [];
        foreach ($exception->errors() as $key => $messages) {
            $errors[preg_replace($pattern, $prefix, $key)] = $messages;
        }
        throw ValidationException::withMessages($errors);
    }

    private function notifySaved(): void
    {
        Notification::make()->title(__('catalog.display.saved'))->success()->send();
    }
}
