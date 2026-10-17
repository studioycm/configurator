<?php

namespace App\Filament\Imports;

use App\Actions\SaveCanonicalOption;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\Value;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeValueImporter extends CatalogImporter
{
    protected static ?string $model = Option::class;

    public static function getColumns(): array
    {
        return [
            static::importColumn(),
            static::textColumn('attribute_key')->label(__('data_exchange.fields.attribute_key'))->exampleHeader('attribute_key')
                ->requiredMapping()->example('thread_type')->rules(['required', 'string', 'max:100', 'exists:attributes,key'])
                ->fillRecordUsing(function (): void {}),
            ImportColumn::make('value_semantic_id')->label(__('data_exchange.fields.value_semantic_id'))->exampleHeader('value_semantic_id')
                ->requiredMapping()->example('bspt')->castStateUsing(fn (mixed $originalState): mixed => $originalState)->rules(['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/D', 'exists:values,semantic_id'])
                ->fillRecordUsing(function (): void {}),
            ImportColumn::make('code')->label(__('catalog.fields.code'))->exampleHeader('code')->requiredMapping()
                ->example('BT')->castStateUsing(fn (mixed $originalState): mixed => $originalState)->rules(['required', 'string', 'regex:/\A[A-Za-z0-9]{2}\z/D']),
            ImportColumn::make('is_active')->label(__('data_exchange.fields.is_active'))->exampleHeader('is_active')
                ->example(1)->castStateUsing(fn (mixed $originalState): mixed => $originalState)->rules(['sometimes', 'required', 'boolean']),
            ImportColumn::make('is_hidden')->label(__('data_exchange.fields.is_hidden'))->exampleHeader('is_hidden')
                ->example(0)->castStateUsing(fn (mixed $originalState): mixed => $originalState)->rules(['sometimes', 'required', 'boolean']),
        ];
    }

    public function resolveRecord(): ?Option
    {
        if ($this->shouldSkipRow()) {
            return null;
        }
        $attributeId = Attribute::where('key', $this->data['attribute_key'] ?? null)->value('id');
        $valueId = Value::where('semantic_id', $this->data['value_semantic_id'] ?? null)->value('id');

        return Option::firstOrNew(['attribute_id' => $attributeId, 'value_id' => $valueId]);
    }

    protected function beforeSave(): void
    {
        if ($this->record->exists && $this->record->isDirty('code')) {
            throw ValidationException::withMessages(['code' => __('data_exchange.validation.existing_code')]);
        }
    }

    public function saveRecord(): void
    {
        DB::transaction(function (): void {
            $this->record = app(SaveCanonicalOption::class)->handle($this->actor(), $this->record->exists ? $this->record : null,
                $this->record->attribute_id, $this->record->value_id, $this->record->code, preserveIdentity: true);
            $this->saveStatus();
        });
    }
}
