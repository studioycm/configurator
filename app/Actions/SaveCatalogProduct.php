<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\User;
use App\Services\CatalogIntegrity;
use App\Services\CatalogRevisions;
use App\Services\ProductEditorData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCatalogProduct
{
    public function __construct(private CatalogIntegrity $integrity, private CatalogRevisions $revisions) {}

    public static function fingerprint(Product $product): string
    {
        return hash('sha256', json_encode($product->getAttributes(), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Product $product, array $data, string $expectedFingerprint, string $expectedGroupFingerprint): Product
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($product, $data, $expectedFingerprint, $expectedGroupFingerprint): Product {
            $groups = $this->integrity->lockGroups();
            $record = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($groups->has($record->group_id), 404);
            if (! hash_equals(self::fingerprint($record), $expectedFingerprint)) {
                throw ValidationException::withMessages(['record' => __('catalog.products.validation.this_product_changed_after_your_editor_was')]);
            }
            $record->setRelation('group', $groups->get($record->group_id));
            if (! hash_equals(ProductEditorData::groupFingerprint($record), $expectedGroupFingerprint)) {
                throw ValidationException::withMessages(['product.group_classification' => __('catalog.products.validation.group_filters_changed_after_your_editor_was')]);
            }
            $rules = [
                'product' => ['required', 'array:product_code,product_name,description,properties,parts,others'],
                'product.product_code' => ['required', 'string', 'max:64', Rule::unique('products', 'product_code')->ignore($record->id)],
                'product.product_name' => ['present', 'nullable', 'string', 'max:255'],
                'product.description' => ['present', 'nullable', 'string', 'max:5000'],
            ];
            foreach (['properties', 'parts', 'others'] as $bucket) {
                $rules['product.'.$bucket] = ['present', 'array', 'list'];
                $rules['product.'.$bucket.'.*'] = ['required', $bucket === 'others' ? 'array:key,value,original_key' : 'array:key,value'];
                $rules['product.'.$bucket.'.*.key'] = ['present', 'string'];
                $rules['product.'.$bucket.'.*.value'] = ['present', 'nullable', 'string'];
            }
            $rules['product.others.*.original_key'] = ['sometimes', 'nullable', 'string'];
            $validated = Validator::make(['product' => $data], $rules)->validate()['product'];
            $profile = ProductEditorData::applyProfile($record, $validated['properties'], $validated['others']);
            if ($profile !== ProductEditorData::rawMap($record, 'profile_fields')) {
                $record->profile_fields = (object) $profile;
            }
            $originalParts = ProductEditorData::rawMap($record, 'parts');
            $parts = ProductEditorData::applyRows($originalParts, $validated['parts'], 'product.parts');
            if ($parts !== $originalParts) {
                $record->parts = str_starts_with(ltrim((string) $record->getRawOriginal('parts')), '{') ? (object) $parts : $parts;
            }
            $record->product_code = $validated['product_code'];
            $record->product_name = $validated['product_name'] ?? '';
            $record->description = $validated['description'] ?? '';
            if ($record->isDirty()) {
                $record->save();
                $this->revisions->advance([$record->group_id]);
            }

            return $record;
        }, attempts: 3);
    }
}
