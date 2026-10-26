<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use League\Csv\Bom;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;
use WeakMap;

class CatalogCsv
{
    /** @var WeakMap<Reader,array{mode:string,enclosure:string,escape:string}> */
    private WeakMap $configuredReaders;

    public function __construct(private CatalogDataExchangePolicy $policy)
    {
        $this->configuredReaders = new WeakMap;
    }

    /** @param resource|string $source @param array{mode?:string,enclosure?:string,escape?:string} $quoting */
    public function reader(mixed $source, ?string $delimiter = null, array $quoting = []): Reader
    {
        $reader = is_string($source) ? Reader::fromString($source) : Reader::from($source);
        $this->configure($reader, $delimiter, $quoting);

        return $reader;
    }

    /** @param array{mode?:string,enclosure?:string,escape?:string} $quoting */
    public function writer(?string $delimiter = null, bool $bom = false, array $quoting = []): Writer
    {
        $writer = Writer::from(new SplTempFileObject);
        $this->configure($writer, $delimiter, $quoting);
        if ($bom) {
            $writer->setOutputBOM(Bom::Utf8);
        }

        return $writer;
    }

    /** @param array{mode?:string,enclosure?:string,escape?:string} $quoting */
    public function configure(Reader|Writer $csv, ?string $delimiter = null, array $quoting = []): void
    {
        $controls = $this->policy->csvControls($delimiter, $quoting);
        if ($csv instanceof Reader) {
            $identity = ['mode' => $quoting['mode'] ?? 'standard', 'enclosure' => $controls['enclosure'], 'escape' => $controls['escape']];
            if (isset($this->configuredReaders[$csv]) && $this->configuredReaders[$csv] !== $identity) {
                throw new InvalidArgumentException('Use a new Reader when changing quoting controls.');
            }
            if (! isset($this->configuredReaders[$csv])) {
                $this->configuredReaders[$csv] = $identity;
                $csv->addFormatter(fn (array $record): array => $this->policy->importRecord($record, $quoting, $controls));
            }
        }
        $csv->setDelimiter($controls['delimiter']);
        $csv->setEnclosure($controls['enclosure']);
        $csv->setEscape($controls['escape']);
    }

    /** Streaming grammar check; no trimming, coercion, row-width rules or record buffering. @return array{sha256: string, bytes: int} */
    public function validate(Reader $reader): array
    {
        $controls = $this->policy->csvControls($reader->getDelimiter(), ['mode' => 'custom', 'enclosure' => $reader->getEnclosure(), 'escape' => $reader->getEscape()]);
        $escaped = false;
        $state = 'start';
        $line = $column = 1;
        $previousCr = false;
        $bytes = 0;
        $hash = hash_init('sha256');
        foreach ($reader->chunk(8192) as $chunk) {
            hash_update($hash, $chunk);
            $bytes += strlen($chunk);
            for ($index = 0, $length = strlen($chunk); $index < $length; $index++) {
                $character = $chunk[$index];
                $newline = $character === "\n" || $character === "\r";
                $error = false;
                if ($state === 'quoted') {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($controls['escape'] !== '' && $character === $controls['escape']) {
                        $escaped = true;
                    } elseif ($character === $controls['enclosure']) {
                        $state = 'closed';
                    }
                } elseif ($state === 'closed') {
                    if ($character === $controls['enclosure']) {
                        $state = 'quoted';
                    } elseif ($character === $controls['delimiter'] || $newline) {
                        $state = 'start';
                    } else {
                        $error = true;
                    }
                } elseif ($character === $controls['delimiter'] || $newline) {
                    $state = 'start';
                } elseif ($character === $controls['enclosure']) {
                    if ($state === 'start') {
                        $state = 'quoted';
                    } else {
                        $error = true;
                    }
                } else {
                    $state = 'unquoted';
                }
                if ($error) {
                    $this->reject($line, $column);
                }
                if ($newline) {
                    if ($character !== "\n" || ! $previousCr) {
                        $line++;
                    }
                    $column = 1;
                } else {
                    $column++;
                }
                $previousCr = $character === "\r";
            }
        }
        if ($state === 'quoted') {
            $this->reject($line, $column);
        }

        return ['sha256' => hash_final($hash), 'bytes' => $bytes];
    }

    private function reject(int $line, int $column): void
    {
        foreach ($this->policy->csvIssues(['grammar_error' => ['line' => $line, 'column' => $column]]) as $issue) {
            throw ValidationException::withMessages(['file' => __('data_exchange.validation.csv_structure', $issue)]);
        }
    }
}
