<?php

return [
    'title' => 'Data Exchange',
    'attribute_values' => 'Attribute-Values',
    'attribute_value' => 'Attribute-Value',
    'actions' => [
        'import' => 'Import CSV',
        'export' => 'Export',
        'export_all' => 'Export all',
        'manage' => 'Manage records',
        'update_existing' => 'Update existing records',
        'restore_spreadsheet_text' => 'Restore text from protected exports',
    ],
    'fields' => [
        'import' => 'Import row',
        'attribute_key' => 'Attribute key',
        'value_semantic_id' => 'Value semantic ID',
        'is_hidden' => 'Hidden',
        'is_active' => 'Active',
    ],
    'help' => [
        'order' => 'Import Values first, Attributes second, then Attribute-Values. Exports here include every shared record.',
        'values' => 'Match by semantic_id. Equal UI Names can represent different Values.',
        'attributes' => 'Match by key. These are shared library records.',
        'attribute_values' => 'Link an existing Attribute key to an existing Value semantic ID. Imports keep existing codes and do not add records to a configurator.',
        'import_flag' => 'Optional: 1 imports the row; 0 skips it. Missing import columns default to 1. Review notes are not imported.',
        'update_existing' => 'Off by default. Existing matches are reported in the failed-row CSV. Only mapped fields are updated; existing Attribute-Value codes stay unchanged.',
        'restore_spreadsheet_text' => 'Use when re-importing Data Exchange exports to restore spreadsheet-protected text. Existing apostrophes are preserved by our export format. Leave off for original CSVs from other sources.',
        'tags' => 'Optional JSON list, for example ["material","legacy_d060"]. Use [] to clear tags.',
    ],
    'validation' => [
        'existing_record' => 'This record already exists. Enable Update existing records to update its mapped fields.',
        'existing_code' => 'This Attribute-Value already has a different code. Existing codes cannot be changed by import.',
    ],
    'notifications' => [
        'import_completed' => ':successful rows processed successfully, including rows skipped with import=0. :failed rows failed. Download the failed-row CSV to review errors.',
        'export_completed' => ':successful rows exported. :failed rows failed to export.',
    ],
];
