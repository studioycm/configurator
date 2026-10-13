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
    public function handle(User $actor, Product $product, array $data, string $expectedFingerprint): Product
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($product, $data, $expectedFingerprint): Product {
            $groups = $this->integrity->lockGroups();
            $record = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($groups->has($record->group_id), 404);
            if (! hash_equals(self::fingerprint($record), $expectedFingerprint)) {
                throw ValidationException::withMessages(['record' => 'This Product changed after your editor was opened. Your draft was kept. Reload the saved record before editing again.']);
            }
            $rules = [
                'product' => ['required', 'array:product_code,product_name,description,properties,parts,extra_data'],
                'product.product_code' => ['required', 'string', 'max:64', Rule::unique('products', 'product_code')->ignore($record->id)],
                'product.product_name' => ['present', 'nullable', 'string', 'max:255'],
                'product.description' => ['present', 'nullable', 'string', 'max:5000'],
            ];
            foreach (['properties', 'parts', 'extra_data'] as $bucket) {
                $rules['product.'.$bucket] = ['present', 'array', 'list'];
                $rules['product.'.$bucket.'.*'] = ['required', 'array:key,value'];
                $rules['product.'.$bucket.'.*.key'] = ['present', 'string'];
                $rules['product.'.$bucket.'.*.value'] = ['present', 'nullable', 'string'];
            }
            $validated = Validator::make(['product' => $data], $rules)->validate()['product'];
            foreach (['properties', 'parts', 'extra_data'] as $bucket) {
                $updated = ProductEditorData::applyRows($record->{$bucket}, $validated[$bucket], 'product.'.$bucket);
                if ($updated !== $record->{$bucket}) {
                    $record->{$bucket} = str_starts_with(ltrim((string) $record->getRawOriginal($bucket)), '{') ? (object) $updated : $updated;
                }
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
