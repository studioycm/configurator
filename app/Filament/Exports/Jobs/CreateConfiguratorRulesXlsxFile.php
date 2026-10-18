<?php

namespace App\Filament\Exports\Jobs;

use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use League\Csv\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class CreateConfiguratorRulesXlsxFile extends CreateXlsxFile
{
    public function handle(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), $this->export->file_name);
        if ($temporaryFile === false) {
            throw new RuntimeException('Unable to create the temporary Rules workbook.');
        }
        $writer = app(Writer::class, ['options' => $this->exporter->getXlsxWriterOptions()]);
        $opened = false;
        $closed = false;
        try {
            $writer->openToFile($temporaryFile);
            $opened = true;
            $this->exporter->configureXlsxWriterAfterOpen($writer);
            $this->writeRows($writer, $this->export->getFileDirectory().DIRECTORY_SEPARATOR.'headers.csv', header: true);
            $disk = $this->export->getFileDisk();
            $files = $disk->files($this->export->getFileDirectory());
            sort($files);
            foreach ($files as $file) {
                if (str_ends_with($file, '.csv') && ! str_ends_with($file, 'headers.csv')) {
                    $this->writeRows($writer, $file);
                }
            }
            $this->exporter->configureXlsxWriterBeforeClose($writer);
            $writer->close();
            $closed = true;
            if ($disk->putFileAs($this->export->getFileDirectory(), new File($temporaryFile), $this->export->file_name.'.xlsx', Filesystem::VISIBILITY_PRIVATE) === false) {
                throw new RuntimeException('Unable to save the private Rules workbook.');
            }
        } catch (Throwable $exception) {
            if ($opened && ! $closed) {
                try {
                    $writer->close();
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    private function writeRows(Writer $writer, string $file, bool $header = false): void
    {
        $stream = $this->export->getFileDisk()->readStream($file);
        if ($stream === false) {
            throw new RuntimeException('Unable to read the private Rules export.');
        }
        try {
            $reader = Reader::from($stream);
            $reader->setDelimiter($this->exporter::getCsvDelimiter());
            $reader->setEscape('');
            $style = $header ? ($this->exporter->getXlsxHeaderCellStyle() ?? $this->exporter->getXlsxCellStyle()) : $this->exporter->getXlsxCellStyle();
            foreach ($reader->getRecords() as $values) {
                $writer->addRow($header ? $this->exporter->makeXlsxHeaderRow($values, $style) : $this->exporter->makeXlsxRow($values, $style));
            }
        } finally {
            fclose($stream);
        }
    }
}
