<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Actions\DeleteConfigurator;
use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\StatusActions;
use App\Livewire\Catalog\ConfiguratorGroups;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Models\Configurator;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class EditConfigurator extends EditRecord
{
    protected static string $resource = ConfiguratorResource::class;

    #[Locked]
    public ?int $initialAttributeId = null;

    #[Locked]
    public ?int $initialRuleId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->initialAttributeId = $this->initialSelection('attribute', 'attributes');
        $this->initialRuleId = $this->initialSelection('rule', 'rules');
    }

    private function initialSelection(string $parameter, string $relationship): ?int
    {
        $value = request()->query($parameter);
        if ($value === null) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($id !== false && $this->getRecord()->{$relationship}()->whereKey($id)->exists(), 404);

        return $id;
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getBreadcrumb(): string
    {
        return __('configurator.labels.manage');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make(__('configurator.resource.singular'))->key('configurator-editor')->persistTabInQueryString('tab')->columnSpanFull()
                ->extraAttributes(['data-configurator-workspace' => $this->getRecord()->id])
                ->extraAlpineAttributes(['x-init' => e('$watch("tab", value => $dispatch("configurator-preview-visibility", {configuratorId: '.$this->getRecord()->id.', tab: value, active: value === $el.querySelector("[data-preview-tab]")?.getAttribute("data-preview-tab-key")}))'), 'x-on:configurator-select-editor-tab' => e('const target = $el.querySelector("[data-preview-editor-tab=" + $event.detail.editor + "]"); if (target) { tab = target.getAttribute("data-preview-editor-tab-key"); if ($event.detail.editor === "preview") $nextTick(() => $el.querySelector("[id^=preview-choices-]")?.focus()) }')])
                ->tabs([
                    Tab::make(__('configurator.workspace.tabs.overview'))->key(fn (Tab $component): string => 'overview::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([Livewire::make(ConfiguratorOverview::class, fn (): array => ['configuratorId' => $this->getRecord()->id])->key('overview-'.$this->getRecord()->id)]),
                    Tab::make(__('catalog.resources.groups.plural'))->key(fn (Tab $component): string => 'groups::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->badge(fn (): int => $this->getRecord()->groups_count ?? $this->getRecord()->groups()->count())->schema([Livewire::make(ConfiguratorGroups::class, fn (): array => ['configuratorId' => $this->getRecord()->id])->key('groups-'.$this->getRecord()->id)->lazy()]),
                    Tab::make(__('catalog.resources.attributes.plural'))->extraAttributes(fn (Tab $component): array => ['data-preview-editor-tab' => 'attributes', 'data-preview-editor-tab-key' => $component->getKey(isAbsolute: false)])->key(fn (Tab $component): string => 'attributes::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->badge(fn (): int => $this->getRecord()->attributes_count ?? $this->getRecord()->attributes()->count())->schema([Livewire::make(AttributesRelationManager::class, fn (): array => ['ownerRecord' => $this->getRecord(), 'pageClass' => static::class, 'initialAttributeId' => $this->initialAttributeId])->key('attributes-'.$this->getRecord()->id)->lazy()]),
                    Tab::make(__('configurator.rules.plural'))->extraAttributes(fn (Tab $component): array => ['data-preview-editor-tab' => 'rules', 'data-preview-editor-tab-key' => $component->getKey(isAbsolute: false)])->key(fn (Tab $component): string => 'rules::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->badge(fn (): int => $this->getRecord()->rules_count ?? $this->getRecord()->rules()->count())->schema([Livewire::make(RulesRelationManager::class, fn (): array => ['ownerRecord' => $this->getRecord(), 'pageClass' => static::class, 'initialRuleId' => $this->initialRuleId])->key('rules-'.$this->getRecord()->id)->lazy()]),
                    Tab::make(__('configurator.workspace.tabs.preview'))->extraAttributes(fn (Tab $component): array => ['data-preview-tab' => true, 'data-preview-tab-key' => $component->getKey(isAbsolute: false), 'data-preview-editor-tab' => 'preview', 'data-preview-editor-tab-key' => $component->getKey(isAbsolute: false)])->key(fn (Tab $component): string => 'preview-test::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([Livewire::make(ConfiguratorPreview::class, fn (): array => ['configuratorId' => $this->getRecord()->id])->key('preview-'.$this->getRecord()->id)->lazy()]),
                ]),
        ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        Gate::authorize('manage-catalog');
        abort(405, __('configurator.validation.use_the_scoped_overview_inclusion_or_rule'));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort(405, __('configurator.validation.use_a_scoped_configurator_action'));
    }

    protected function getHeaderActions(): array
    {
        return [
            StatusActions::make(Action::make('changeStatus')->record(fn (): Model => $this->getRecord()), Configurator::class),
            Action::make('duplicate')->label(__('ui.actions.duplicate'))->authorize('manage-catalog')->schema([TextInput::make('name')->label(__('ui.fields.name'))->required()->maxLength(255)])
                ->fillForm(fn (): array => ['name' => __('configurator.actions.copy_name', ['name' => $this->getRecord()->name])])
                ->action(function (array $data): void {
                    $copy = ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->duplicate(auth()->user(), $this->getRecord(), $data['name']), $this->getMountedActionSchema());
                    $this->redirect(ConfiguratorResource::getUrl('edit', ['record' => $copy]));
                }),
            Action::make('remove')->label(__('ui.actions.delete'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->schema([View::make('filament.forms.validation-summary')])
                ->extraModalFooterActions(fn (): array => $this->getRecord()->groups()->exists() ? [Action::make('blockingGroups')->label(__('configurator.labels.view_assigned_groups'))->slideOver()->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.back'))->modalContent(fn () => view('filament.resources.item-list-content', ['listKey' => 'configurator-groups', 'parentId' => $this->getRecord()->id]))] : [])
                ->modalSubmitAction(fn (Action $action): Action => $action->disabled($this->getRecord()->groups()->exists()))
                ->modalDescription(__('configurator.help.unassign_groups_before_deleting_this_configurator_local'))
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteConfigurator::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(ConfiguratorResource::getUrl());
                }),
        ];
    }

    #[On('configurator-updated')]
    public function refreshConfigurator(): void
    {
        Gate::authorize('manage-catalog');
        $this->record = Configurator::withCount(['groups', 'attributes', 'rules'])->findOrFail($this->getRecord()->id);
    }
}
