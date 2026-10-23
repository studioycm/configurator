<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('products')->orderBy('id')->lazyById(100) as $product) {
            $this->encode($this->mergeFields($product));
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->json('profile_fields')->nullable();
        });
        foreach (DB::table('products')->orderBy('id')->lazyById(100) as $product) {
            DB::table('products')->where('id', $product->id)->update(['profile_fields' => $this->encode($this->mergeFields($product))]);
        }
        Schema::table('products', function (Blueprint $table): void {
            $table->json('profile_fields')->nullable(false)->change();
            $table->dropColumn(['properties', 'extra_data']);
        });
    }

    public function down(): void
    {
        foreach (DB::table('products')->orderBy('id')->lazyById(100) as $product) {
            $this->encode($this->fields($product->profile_fields, $product->id));
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->json('properties')->nullable();
            $table->json('extra_data')->nullable();
        });
        $extraKeys = ['product_pic', 'sub_group', 'Addition_to_Product_Code', 'Strainer', 'Indc', '_unnamed_column_48', 'MyIndc', 'blank1', 'blank2', 'blank3', 'Connection_Type_Layout', 'product_supplier', 'product_standard', 'standard_compliancy', 'with_s50c'];
        foreach (DB::table('products')->orderBy('id')->lazyById(100) as $product) {
            $properties = new stdClass;
            $extraData = new stdClass;
            foreach ($this->fields($product->profile_fields, $product->id) as $key => $value) {
                if (in_array((string) $key, $extraKeys, true)) {
                    $extraData->{(string) $key} = $value;
                } else {
                    $properties->{(string) $key} = $value;
                }
            }
            DB::table('products')->where('id', $product->id)->update(['properties' => $this->encode($properties), 'extra_data' => $this->encode($extraData)]);
        }
        Schema::table('products', function (Blueprint $table): void {
            $table->json('properties')->nullable(false)->change();
            $table->json('extra_data')->nullable(false)->change();
            $table->dropColumn('profile_fields');
        });
    }

    private function mergeFields(stdClass $product): stdClass
    {
        $fields = $this->fields($product->properties, $product->id);
        foreach ($this->fields($product->extra_data, $product->id) as $key => $value) {
            $key = (string) $key;
            if (property_exists($fields, $key) && ! $this->sameValue($fields->{$key}, $value)) {
                throw new RuntimeException('Cannot consolidate product '.$product->id.': conflicting profile key '.$this->encode($key).'.');
            }
            $fields->{$key} = $value;
        }

        return $fields;
    }

    private function fields(string $json, int $productId): stdClass
    {
        $fields = json_decode($json, flags: JSON_THROW_ON_ERROR);
        if (! $fields instanceof stdClass && ! is_array($fields)) {
            throw new RuntimeException('Cannot consolidate product '.$productId.': profile fields must be a JSON map.');
        }
        $pattern = '~"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?~s';
        if (preg_match_all($pattern, $json, $originalNumbers) === false
            || preg_match_all($pattern, $this->encode($fields), $encodedNumbers) === false
            || $originalNumbers[0] !== $encodedNumbers[0]) {
            throw new RuntimeException('Cannot consolidate product '.$productId.': numeric JSON values cannot be preserved exactly.');
        }

        return (object) $fields;
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        if (get_debug_type($left) !== get_debug_type($right)) {
            return false;
        }
        if ($left instanceof stdClass || is_array($left)) {
            $left = (array) $left;
            $right = (array) $right;
            if (count($left) !== count($right)) {
                return false;
            }
            foreach ($left as $key => $value) {
                if (! array_key_exists($key, $right) || ! $this->sameValue($value, $right[$key])) {
                    return false;
                }
            }

            return true;
        }

        return $left === $right;
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
};
