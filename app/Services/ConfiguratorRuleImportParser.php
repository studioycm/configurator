<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;
use ZipArchive;

class ConfiguratorRuleImportParser
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_ROWS = 5000;

    /** @return list<array<string, string>> */
    public function parse(string $path): array
    {
        $this->check(is_file($path) && is_readable($path), 'The Rules file is not readable.');
        $this->check(filesize($path) <= self::MAX_BYTES, 'Rules files must be at most 10 MB.');
        $stream = fopen($path, 'rb');
        $signature = fread($stream, 4);
        rewind($stream);
        try {
            if ($signature === "PK\x03\x04") {
                return $this->xlsx($path);
            }
            if (str_starts_with($signature, "\xEF\xBB\xBF")) {
                fseek($stream, 3);
            }
            $headers = fgetcsv($stream, escape: '');
            $this->check(is_array($headers), 'The Rules file is empty.');
            $this->headers($headers);
            $rows = [];
            while (($values = fgetcsv($stream, escape: '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                $this->check(count($values) === count($headers), 'CSV row '.(count($rows) + 2).' has an incorrect column count.');
                $this->check(count($rows) < self::MAX_ROWS, 'Rules files must contain at most 5000 data rows.');
                $rows[] = array_combine($headers, $values);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    /** @return list<array<string, string>> */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        $this->check($zip->open($path) === true, 'The XLSX file cannot be opened.');
        try {
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expanded += $zip->statIndex($index)['size'];
                $this->check($expanded <= 50 * 1024 * 1024, 'The expanded XLSX file exceeds 50 MB.');
            }
            $formulaCells = $this->formulaCells($zip);
        } finally {
            $zip->close();
        }
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Rules') {
                    continue;
                }
                $headers = null;
                $rows = [];
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $values = [];
                    foreach ($row->getCells() as $column => $cell) {
                        if ($headers !== null && str_starts_with($headers[$column] ?? '', 'helper_')) {
                            $values[] = '';

                            continue;
                        }
                        $this->check(! isset($formulaCells[$rowNumber][$column]) && ! $cell instanceof ErrorCell, 'The Rules sheet contains formulas or errors. Paste values as text before importing; cached formula results are not imported.');
                        $value = $cell->getValue();
                        $this->check($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value), 'Use text cells in the Rules sheet; dates and other typed cells are not supported.');
                        $values[] = $value === null ? '' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
                    }
                    if ($headers === null) {
                        $this->headers($values);
                        $headers = $values;

                        continue;
                    }
                    if (array_filter($values, fn (string $value): bool => $value !== '') === []) {
                        continue;
                    }
                    $this->check(count($values) <= count($headers), 'The Rules sheet contains extra cells outside the headers.');
                    $this->check(count($rows) < self::MAX_ROWS, 'Rules files must contain at most 5000 data rows.');
                    $rows[] = array_combine($headers, array_pad($values, count($headers), ''));
                }
                $this->check($headers !== null, 'The Rules sheet is empty.');

                return $rows;
            }
            throw ValidationException::withMessages(['file' => 'The workbook must contain a sheet named Rules.']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'The workbook could not be read. Save a valid XLSX file with a Rules sheet.']);
        } finally {
            $reader->close();
        }
    }

    /** @return array<int, array<int, true>> */
    private function formulaCells(ZipArchive $zip): array
    {
        $load = function (string $path) use ($zip): DOMXPath {
            $xml = $zip->getFromName($path);
            $this->check(is_string($xml) && ! str_contains($xml, '<!DOCTYPE'), 'Invalid XLSX XML document.');
            $document = new DOMDocument;
            $this->check(@$document->loadXML($xml, LIBXML_NONET), 'Invalid XLSX XML document.');

            return new DOMXPath($document);
        };
        $workbook = $load('xl/workbook.xml');
        $relationship = null;
        foreach ($workbook->query('//*[local-name()="sheet"]') as $sheet) {
            if ($sheet->getAttribute('name') === 'Rules') {
                $relationship = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            }
        }
        $this->check($relationship !== null, 'The workbook must contain a sheet named Rules.');
        $relations = $load('xl/_rels/workbook.xml.rels');
        $target = null;
        foreach ($relations->query('//*[local-name()="Relationship"]') as $relation) {
            if ($relation->getAttribute('Id') === $relationship) {
                $this->check($relation->getAttribute('TargetMode') !== 'External', 'Rules must be a local workbook sheet.');
                $target = $relation->getAttribute('Target');
            }
        }
        $this->check(is_string($target) && $target !== '' && ! str_contains($target, '..'), 'Invalid Rules sheet reference.');
        $sheet = $load(str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target);
        $formulas = [];
        foreach ($sheet->query('//*[local-name()="c"][*[local-name()="f"]]') as $cell) {
            $this->check(preg_match('/^([A-Z]+)([1-9][0-9]*)$/D', $cell->getAttribute('r'), $matches) === 1, 'Invalid formula cell address.');
            $column = 0;
            foreach (str_split($matches[1]) as $letter) {
                $column = $column * 26 + ord($letter) - 64;
            }
            $formulas[(int) $matches[2]][$column - 1] = true;
        }

        return $formulas;
    }

    /** @param list<array<string, string>> $rows @return list<array<string, string>> */
    public function normalize(array $rows, bool $restoreSpreadsheetText = false): array
    {
        $this->check(array_is_list($rows) && count($rows) <= self::MAX_ROWS, 'Rules files must contain at most 5000 data rows.');
        $normalized = [];
        $bytes = 0;
        foreach ($rows as $row) {
            $this->check(is_array($row), 'Each Rules row must contain all 28 core columns.');
            $this->headers(array_keys($row));
            $clean = [];
            foreach (ConfiguratorRuleExchange::COLUMNS as $field) {
                $value = $row[$field];
                $this->check(is_string($value) && mb_check_encoding($value, 'UTF-8'), 'Rules cells must contain valid UTF-8 text.');
                $bytes += strlen($value);
                $this->check($bytes <= self::MAX_BYTES, 'Rules cell contents must be at most 10 MB.');
                if ($restoreSpreadsheetText && preg_match("/\A'+[=+\-@\t\r]/", $value)) {
                    $value = substr($value, 1);
                }
                $clean[$field] = $value;
            }
            $normalized[] = $clean;
        }

        return $normalized;
    }

    /** @param list<string> $headers */
    private function headers(array $headers): void
    {
        $this->check(count($headers) === count(array_unique($headers, SORT_STRING)), 'Duplicate Rules headers are not allowed.');
        $this->check(array_diff(ConfiguratorRuleExchange::COLUMNS, $headers) === [], 'All 28 rules-rows-v1 core headers are required.');
        foreach ($headers as $header) {
            $this->check(is_string($header) && (in_array($header, ConfiguratorRuleExchange::COLUMNS, true) || str_starts_with($header, 'helper_')), 'Unknown Rules header: '.(string) $header);
        }
    }

    private function check(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['file' => $message]);
        }
    }
}
