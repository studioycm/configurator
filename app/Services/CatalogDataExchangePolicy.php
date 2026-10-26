<?php

namespace App\Services;

use App\Filament\Exports\AttributeExporter;
use App\Filament\Exports\AttributeValueExporter;
use App\Filament\Exports\ValueExporter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Canonical exchange decisions. I/O, authorization and queue mechanics belong to their existing owners. */
class CatalogDataExchangePolicy
{
    public const VERSION = 1;

    /** @return list<class-string> */
    public function canonicalExporters(): array
    {
        return [ValueExporter::class, AttributeExporter::class, AttributeValueExporter::class];
    }

    /** CSV-01/02: standard export/default import; legacy and custom import quoting are explicit choices, never guesses.
     * @param  array{mode?:string,enclosure?:string,escape?:string}  $quoting
     * @return array{delimiter:string,enclosure:string,escape:string}
     */
    public function csvControls(?string $delimiter = null, array $quoting = []): array
    {
        $delimiter ??= ',';
        $mode = $quoting['mode'] ?? 'standard';
        if (! in_array($mode, ['standard', 'legacy', 'legacy_convert', 'custom'], true)) {
            throw new InvalidArgumentException('Unsupported CSV quoting mode.');
        }
        $enclosure = $mode === 'custom' ? ($quoting['enclosure'] ?? '"') : '"';
        $escape = match ($mode) {
            'legacy', 'legacy_convert' => '\\',
            'custom' => $quoting['escape'] ?? '',
            default => '',
        };
        if (strlen($delimiter) !== 1 || strlen($enclosure) !== 1 || strlen($escape) > 1
            || str_contains("\r\n", $delimiter) || str_contains("\r\n", $enclosure) || ($escape !== '' && str_contains("\r\n", $escape))
            || $delimiter === $enclosure || $escape === $enclosure) {
            throw new InvalidArgumentException('Choose distinct single-byte CSV separator and enclosure characters, and an empty or single-byte escape.');
        }

        return ['delimiter' => $delimiter, 'enclosure' => $enclosure, 'escape' => $escape];
    }

    /** CSV-01–04: the same rule owner supplies the enabled-rule list shown before import.
     * @param  array{mode?:string,enclosure?:string,escape?:string,restore_spreadsheet_text?:bool,update_existing?:bool}  $settings
     * @return list<string>
     */
    public function importRuleCodes(array $settings): array
    {
        $mode = $settings['mode'] ?? 'standard';

        return ['whole_file', $mode === 'legacy' ? 'legacy' : ($mode === 'legacy_convert' ? 'legacy_convert' : ($mode === 'custom' ? 'custom' : 'standard')), 'exact_text', 'import_flag',
            ($settings['update_existing'] ?? false) ? 'update_existing' : 'existing_unchanged',
            ($settings['restore_spreadsheet_text'] ?? false) ? 'restore_text' : 'original_text', 'standard_output'];
    }

    /** CSV-02: explicit value conversion only; do not collapse path backslashes or interpret other escape sequences.
     * @param  array<string|int,mixed>  $record
     * @param  array{mode?:string,enclosure?:string,escape?:string}  $quoting
     * @param  array{delimiter:string,enclosure:string,escape:string}  $controls
     * @return array<string|int,mixed>
     */
    public function importRecord(array $record, array $quoting, array $controls): array
    {
        if (($quoting['mode'] ?? 'standard') !== 'legacy_convert') {
            return $record;
        }

        return array_map(fn (mixed $value): mixed => is_string($value) ? str_replace($controls['escape'].$controls['enclosure'], $controls['enclosure'], $value) : $value, $record);
    }

    /** CSV-03/04: mechanics report enclosure errors only, without adding row-width or value restrictions. @param array{grammar_error?: array{line: int, column: int}} $facts @return list<array{code: string, line: int, column: int}> */
    public function csvIssues(array $facts): array
    {
        return isset($facts['grammar_error']) ? [['code' => 'csv_structure', ...$facts['grammar_error']]] : [];
    }

    /**
     * EXP-01–09: one decision owner for completion and both downloaders. A verified workbook is independent of preparation CSV availability.
     *
     * @param  array{version?: ?int, sealed?: bool, evidence_valid?: bool, planned?: int, successful?: int, rejected?: int, unconfirmed?: int, commit_outcome?: string, csv?: array{verified?: bool, publication?: string}, xlsx?: array{verified?: bool, publication?: string}, historical?: array{consistent?: bool, csv?: bool, xlsx?: bool}}  $facts
     * @return array{planned: int, successful: int, rejected: int, unconfirmed: int, partial: bool, csv: array{state: string, download: bool}, xlsx: array{state: string, download: bool}}
     */
    public function assessExport(array $facts): array
    {
        $planned = max(0, $facts['planned'] ?? 0);
        $successful = max(0, $facts['successful'] ?? 0);
        $rejected = max(0, $facts['rejected'] ?? 0);
        $unconfirmed = max(0, $facts['unconfirmed'] ?? $planned);
        $result = ['planned' => $planned, 'successful' => $successful, 'rejected' => $rejected, 'unconfirmed' => $unconfirmed, 'partial' => $rejected > 0 && $successful > 0];
        foreach (['csv', 'xlsx'] as $format) {
            $state = 'unconfirmed';
            if (($facts['version'] ?? null) === null) {
                $state = ($facts['historical']['consistent'] ?? false) && ($facts['historical'][$format] ?? false) ? 'historical' : 'unconfirmed';
            } elseif ($facts['version'] === self::VERSION && ($facts['sealed'] ?? false) && ($facts['evidence_valid'] ?? false)) {
                $complete = $unconfirmed === 0 && $planned === $successful + $rejected;
                if (($facts['commit_outcome'] ?? 'unknown') === 'pending-enclosing-commit') {
                    $state = 'pending';
                } elseif ($complete && ($facts['commit_outcome'] ?? 'unknown') === 'known-committed') {
                    $artifact = $facts[$format] ?? [];
                    $state = match (true) {
                        $successful === 0 => 'no-data',
                        ($artifact['verified'] ?? false) && ($artifact['publication'] ?? 'unknown') === 'published' => 'ready',
                        ($artifact['publication'] ?? 'unknown') === 'failed' => 'failed',
                        ($artifact['publication'] ?? 'unknown') === 'staged' => 'pending',
                        default => 'unconfirmed',
                    };
                }
            }
            $result[$format] = ['state' => $state, 'download' => in_array($state, ['ready', 'historical'], true)];
        }

        return $result;
    }

    /** EXP-05/08: requested formats determine progress and severity; an unrequested workbook is never called Preparing.
     * @param  array{successful:int,rejected:int,partial:bool,csv:array{state:string,download:bool},xlsx:array{state:string,download:bool}}  $assessment
     * @param  list<string>  $formats
     * @return array{phase:string,status:string}
     */
    public function notification(array $assessment, array $formats): array
    {
        $states = array_map(fn (string $format): string => $assessment[$format]['state'], $formats);
        if ($states !== [] && array_all($states, fn (string $state): bool => $state === 'no-data')) {
            return ['phase' => 'no_data', 'status' => 'warning'];
        }
        if (in_array('unconfirmed', $states, true)) {
            return ['phase' => 'unconfirmed', 'status' => 'warning'];
        }
        if (in_array('failed', $states, true)) {
            return ['phase' => 'failed', 'status' => array_any($formats, fn (string $format): bool => $assessment[$format]['download']) ? 'warning' : 'danger'];
        }
        if (in_array('pending', $states, true)) {
            return ['phase' => 'preparing', 'status' => $assessment['rejected'] > 0 ? 'warning' : 'info'];
        }

        return ['phase' => 'finished', 'status' => $assessment['partial'] || in_array('historical', $states, true) ? 'warning' : 'success'];
    }

    /** EXP-07: reuse evidence, never rerender newer source values under an adopted identity. @param array{capture_verified?: bool, conflict?: bool, known_rollback?: bool, unconfirmed?: bool} $facts */
    public function retryDecision(array $facts): string
    {
        return match (true) {
            $facts['conflict'] ?? false => 'deny',
            $facts['capture_verified'] ?? false => 'reuse-verified-capture',
            $facts['known_rollback'] ?? false => 'retry-known-rollback',
            $facts['unconfirmed'] ?? false => 'inspect-unconfirmed',
            default => 'deny',
        };
    }

    /** EXP-04: only positively classified formatter data errors are row rejections; storage/SQL/queue failures are unconfirmed work. */
    public function isFormatterRejection(\Throwable $exception): bool
    {
        return $exception instanceof ValidationException || $exception instanceof InvalidArgumentException || $exception instanceof \UnexpectedValueException;
    }

    /** MODE-01: the optional streaming worker remains unavailable pending capacity/admission review. */
    public function executionMode(): string
    {
        return 'chunked';
    }
}
