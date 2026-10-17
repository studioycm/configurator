<?php

namespace App\Filament\Imports;

use App\Actions\SaveCanonicalDefinition;
use App\Models\Value;
use Filament\Actions\Imports\ImportColumn;

class ValueImporter extends CatalogImporter
{
    protected static ?string $model = Value::class;

    public static function getColumns(): array
    {
        return [
            static::importColumn(),
            ImportColumn::make('semantic_id')->label(__('catalog.fields.semantic_id'))->exampleHeader('semantic_id')
                ->requiredMapping()->example('nylon')->castStateUsing(fn (mixed $originalState): mixed => $originalState)->rules(['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/D']),
            static::textColumn('label')->label(__('catalog.fields.ui_name'))->exampleHeader('label')
                ->requiredMapping()->example('Nylon')->rules(['required', 'string', 'max:255']),
            static::textColumn('description')->label(__('catalog.fields.specification'))->exampleHeader('description')
                ->example('')->rules(['nullable', 'string', 'max:5000']),
            ImportColumn::make('tags')->label(__('catalog.fields.tags'))->exampleHeader('tags')->example('[]')
                ->helperText(__('data_exchange.help.tags'))
                ->castStateUsing(fn (mixed $state): mixed => blank($state) ? [] : (is_string($state) ? (json_decode($state, true) ?? $state) : $state))
                ->rules(['array', 'list', 'max:30'])
                ->nestedRecursiveRules(['required', 'string', 'max:80', 'distinct']),
        ];
    }

    public function resolveRecord(): ?Value
    {
        if ($this->shouldSkipRow()) {
            return null;
        }

        return Value::firstOrNew(['semantic_id' => $this->data['semantic_id'] ?? null]);
    }

    public function saveRecord(): void
    {
        $this->record = app(SaveCanonicalDefinition::class)->handle($this->actor(), $this->record,
            $this->mappedData(['semantic_id', 'label', 'description', 'tags']));
    }
}
