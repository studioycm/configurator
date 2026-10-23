<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProductFieldQuery
{
    /** @return array{value: string, type: string, binding: string, stringType: string} */
    public static function expressions(string $key): array
    {
        if (! ProductFields::validKey($key)) {
            throw new \InvalidArgumentException(__('catalog.messages.unregistered_catalog_property'));
        }
        if (DB::getDriverName() === 'mysql') {
            return [
                'value' => 'JSON_UNQUOTE(JSON_EXTRACT(profile_fields, ?)) COLLATE utf8mb4_0900_bin',
                'type' => 'JSON_TYPE(JSON_EXTRACT(profile_fields, ?))',
                'binding' => '$.'.json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'stringType' => 'STRING',
            ];
        }

        return [
            'value' => '(SELECT field.value FROM json_each(profile_fields) AS field WHERE CAST(field.key AS TEXT) = ? COLLATE BINARY LIMIT 1) COLLATE BINARY',
            'type' => '(SELECT field.type FROM json_each(profile_fields) AS field WHERE CAST(field.key AS TEXT) = ? COLLATE BINARY LIMIT 1)',
            'binding' => $key,
            'stringType' => 'text',
        ];
    }
}
