<?php

use Illuminate\Support\Facades\Schema;

test('historical Bolt migrations retain their table namespace without the package', function () {
    foreach (['categories', 'collections', 'forms', 'sections', 'fields', 'responses', 'field_responses'] as $table) {
        expect(Schema::hasTable('bolt_'.$table))->toBeTrue()
            ->and(Schema::hasTable($table))->toBeFalse();
    }
});
