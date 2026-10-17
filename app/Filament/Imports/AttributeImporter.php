<?php

namespace App\Filament\Imports;

use App\Actions\SaveCanonicalDefinition;
use App\Models\Attribute;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Facades\DB;

class AttributeImporter extends CatalogImporter
{
    protected static ?string $model = Attribute::class;

    public static function getColumns(): array
    {
        return [
            static::importColumn(),
            static::textColumn('key')->label(__('catalog.fields.key'))->exampleHeader('key')->requiredMapping()
                ->example('thread_type')->rules(['required', 'string', 'max:100']),
            static::textColumn('label')->label(__('catalog.fields.ui_name'))->exampleHeader('label')->requiredMapping()
                ->example('Thread Type')->rules(['required', 'string', 'max:255']),
            ImportColumn::make('is_active')->label(__('data_exchange.fields.is_active'))->exampleHeader('is_active')
                ->example(1)->castStateUsing(fn (mixed $originalState): mixed => $originalState)->rules(['sometimes', 'required', 'boolean']),
        ];
    }

    public function resolveRecord(): ?Attribute
    {
        if ($this->shouldSkipRow()) {
            return null;
        }

        return Attribute::firstOrNew(['key' => $this->data['key'] ?? null]);
    }

    public function saveRecord(): void
    {
        DB::transaction(function (): void {
            $this->record = app(SaveCanonicalDefinition::class)->handle($this->actor(), $this->record, $this->mappedData(['key', 'label']));
            $this->saveStatus();
        });
    }
}
