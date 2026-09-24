<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupSettings;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditGroup extends EditRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openCatalog')->label('Open catalog page')
                ->url(fn (): string => route('catalog.groups.show', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['catalog_settings'] = GroupForm::settingsState($this->getRecord());

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->refresh();
        $this->fillForm();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data): Model {
                $settings = $data['catalog_settings'] ?? null;
                unset($data['catalog_settings']);
                $group = app(SaveCatalogGroup::class)->handle(auth()->user(), $record, $data);
                if ($settings !== null) {
                    $settings['result_settings']['page_size_options'] = array_column($settings['result_settings']['page_size_options'], 'size');
                    $group = app(SaveGroupSettings::class)->handle(auth()->user(), $group, $settings);
                }

                return $group;
            }, attempts: 3);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $key = str_starts_with($key, 'settings.') ? 'catalog_settings.'.substr($key, 9) : preg_replace('/^group\./', '', $key);
                $errors['data.'.$key] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
