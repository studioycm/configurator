<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class CatalogImportParser
{
    public const string FORMAT = 'ari-canonical-csv-v1';

    /** @var list<array{header: string, bucket: string, key: string}> */
    public const array COLUMNS = [
        ['header' => 'id', 'bucket' => 'core', 'key' => 'legacy_id'],
        ['header' => 'group_id', 'bucket' => 'core', 'key' => 'legacy_group_id'],
        ['header' => 'group_name', 'bucket' => 'group', 'key' => 'group_name'],
        ['header' => 'product_code', 'bucket' => 'core', 'key' => 'product_code'],
        ['header' => 'Description', 'bucket' => 'core', 'key' => 'description'],
        ['header' => 'product_pic', 'bucket' => 'extra_data', 'key' => 'product_pic'],
        ['header' => 'sub_group', 'bucket' => 'extra_data', 'key' => 'sub_group'],
        ['header' => 'Working_Pressure', 'bucket' => 'properties', 'key' => 'Working_Pressure'],
        ['header' => 'Valve_Type', 'bucket' => 'properties', 'key' => 'Valve_Type'],
        ['header' => 'Connection_Type', 'bucket' => 'properties', 'key' => 'Connection_Type'],
        ['header' => 'Connection_Size', 'bucket' => 'properties', 'key' => 'Connection_Size'],
        ['header' => 'Automatic_Type', 'bucket' => 'properties', 'key' => 'Automatic_Type'],
        ['header' => 'Automatic_Config', 'bucket' => 'properties', 'key' => 'Automatic_Config'],
        ['header' => 'Discharge_Outlet_Type', 'bucket' => 'properties', 'key' => 'Discharge_Outlet_Type'],
        ['header' => 'Addition_to_Product_Code', 'bucket' => 'extra_data', 'key' => 'Addition_to_Product_Code'],
        ['header' => 'Strainer', 'bucket' => 'extra_data', 'key' => 'Strainer'],
        ['header' => 'Model', 'bucket' => 'properties', 'key' => 'Model'],
        ['header' => 'Max_W_Temp', 'bucket' => 'properties', 'key' => 'Max_W_Temp'],
        ['header' => 'Part1', 'bucket' => 'parts', 'key' => 'Part1'],
        ['header' => 'Part2', 'bucket' => 'parts', 'key' => 'Part2'],
        ['header' => 'Part3', 'bucket' => 'parts', 'key' => 'Part3'],
        ['header' => 'Part4', 'bucket' => 'parts', 'key' => 'Part4'],
        ['header' => 'Part5', 'bucket' => 'parts', 'key' => 'Part5'],
        ['header' => 'Part6', 'bucket' => 'parts', 'key' => 'Part6'],
        ['header' => 'Part7', 'bucket' => 'parts', 'key' => 'Part7'],
        ['header' => 'Part8', 'bucket' => 'parts', 'key' => 'Part8'],
        ['header' => 'Part9', 'bucket' => 'parts', 'key' => 'Part9'],
        ['header' => 'Part10', 'bucket' => 'parts', 'key' => 'Part10'],
        ['header' => 'Part11', 'bucket' => 'parts', 'key' => 'Part11'],
        ['header' => 'Part12', 'bucket' => 'parts', 'key' => 'Part12'],
        ['header' => 'Part13', 'bucket' => 'parts', 'key' => 'Part13'],
        ['header' => 'Part14', 'bucket' => 'parts', 'key' => 'Part14'],
        ['header' => 'Part15', 'bucket' => 'parts', 'key' => 'Part15'],
        ['header' => 'Part16', 'bucket' => 'parts', 'key' => 'Part16'],
        ['header' => 'Part17', 'bucket' => 'parts', 'key' => 'Part17'],
        ['header' => 'Part18', 'bucket' => 'parts', 'key' => 'Part18'],
        ['header' => 'Part19', 'bucket' => 'parts', 'key' => 'Part19'],
        ['header' => 'A', 'bucket' => 'properties', 'key' => 'A'],
        ['header' => 'B', 'bucket' => 'properties', 'key' => 'B'],
        ['header' => 'OA', 'bucket' => 'properties', 'key' => 'OA'],
        ['header' => 'WT', 'bucket' => 'properties', 'key' => 'WT'],
        ['header' => 'AV', 'bucket' => 'properties', 'key' => 'AV'],
        ['header' => 'Product_Name', 'bucket' => 'core', 'key' => 'product_name'],
        ['header' => 'Indc', 'bucket' => 'extra_data', 'key' => 'Indc'],
        ['header' => 'Auto_Flow_Type', 'bucket' => 'properties', 'key' => 'Auto_Flow_Type'],
        ['header' => 'E', 'bucket' => 'properties', 'key' => 'E'],
        ['header' => 'D_out', 'bucket' => 'properties', 'key' => 'D_out'],
        ['header' => '', 'bucket' => 'extra_data', 'key' => '_unnamed_column_48'],
        ['header' => 'MyIndc', 'bucket' => 'extra_data', 'key' => 'MyIndc'],
        ['header' => 'blank1', 'bucket' => 'extra_data', 'key' => 'blank1'],
        ['header' => 'blank2', 'bucket' => 'extra_data', 'key' => 'blank2'],
        ['header' => 'blank3', 'bucket' => 'extra_data', 'key' => 'blank3'],
        ['header' => 'Connection_Type_Layout', 'bucket' => 'extra_data', 'key' => 'Connection_Type_Layout'],
        ['header' => 'Part20', 'bucket' => 'parts', 'key' => 'Part20'],
        ['header' => 'product_supplier', 'bucket' => 'extra_data', 'key' => 'product_supplier'],
        ['header' => 'product_standard', 'bucket' => 'extra_data', 'key' => 'product_standard'],
        ['header' => 'standard_compliancy', 'bucket' => 'extra_data', 'key' => 'standard_compliancy'],
        ['header' => 'C', 'bucket' => 'properties', 'key' => 'C'],
        ['header' => 'Part21', 'bucket' => 'parts', 'key' => 'Part21'],
        ['header' => 'Part22', 'bucket' => 'parts', 'key' => 'Part22'],
        ['header' => 'Part23', 'bucket' => 'parts', 'key' => 'Part23'],
        ['header' => 'Part24', 'bucket' => 'parts', 'key' => 'Part24'],
        ['header' => 'Part25', 'bucket' => 'parts', 'key' => 'Part25'],
        ['header' => 'Part26', 'bucket' => 'parts', 'key' => 'Part26'],
        ['header' => 'Part27', 'bucket' => 'parts', 'key' => 'Part27'],
        ['header' => 'Part28', 'bucket' => 'parts', 'key' => 'Part28'],
        ['header' => 'with_s50c', 'bucket' => 'extra_data', 'key' => 'with_s50c'],
    ];

    /** @return list<string> */
    public static function propertyKeys(): array
    {
        return array_column(array_filter(self::COLUMNS, fn (array $column): bool => $column['bucket'] === 'properties'), 'key');
    }

    public static function mapHash(): string
    {
        return hash('sha256', json_encode([self::FORMAT, self::COLUMNS], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{source_hash: string, map_hash: string, format: string, groups: array<string, string>, rows: list<array{row: int, data: array<string, mixed>}>}
     */
    public function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['source' => 'The canonical CSV is not readable.']);
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['source' => 'The canonical CSV must contain valid UTF-8 text without null bytes.']);
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $contents);
        rewind($stream);
        try {
            if (fgetcsv($stream, escape: '') !== array_column(self::COLUMNS, 'header')) {
                throw ValidationException::withMessages(['source' => 'The 67 positional headers do not match the canonical map, including the blank header at position 48.']);
            }
            $rows = [];
            $groups = [];
            $identities = [];
            $codes = [];
            $line = 1;
            while (($values = fgetcsv($stream, escape: '')) !== false) {
                $line++;
                if (count($values) !== count(self::COLUMNS)) {
                    throw ValidationException::withMessages(['source' => 'CSV row '.$line.' must contain exactly 67 positions.']);
                }
                foreach ([0 => 64, 1 => 64, 2 => 255, 3 => 64, 42 => 255] as $position => $length) {
                    if (($position !== 42 && $values[$position] === '') || mb_strlen($values[$position]) > $length) {
                        throw ValidationException::withMessages(['source' => 'Invalid required field or length at row '.$line.', column '.($position + 1).'.']);
                    }
                }
                if (isset($identities['id:'.$values[0]]) || isset($codes['code:'.$values[3]])) {
                    throw ValidationException::withMessages(['source' => 'Duplicate Product identity or code at row '.$line.'.']);
                }
                if (isset($groups[$values[1]]) && $groups[$values[1]] !== $values[2]) {
                    throw ValidationException::withMessages(['source' => 'Conflicting Group names at row '.$line.'.']);
                }
                $identities['id:'.$values[0]] = true;
                $codes['code:'.$values[3]] = true;
                $groups[$values[1]] = $values[2];
                $data = ['properties' => [], 'parts' => [], 'extra_data' => []];
                foreach (self::COLUMNS as $position => $column) {
                    if ($column['bucket'] === 'core') {
                        $data[$column['key']] = $values[$position];
                    } elseif ($column['bucket'] !== 'group') {
                        $data[$column['bucket']][$column['key']] = $values[$position];
                    }
                }
                $rows[] = ['row' => $line, 'data' => $data];
            }

            return ['source_hash' => hash('sha256', $contents), 'map_hash' => self::mapHash(), 'format' => self::FORMAT, 'groups' => $groups, 'rows' => $rows];
        } finally {
            fclose($stream);
        }
    }
}
