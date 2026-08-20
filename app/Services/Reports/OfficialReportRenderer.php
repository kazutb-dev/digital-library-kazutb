<?php

namespace App\Services\Reports;

use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Support\Csv;
use App\Support\OfficeOpenXmlExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

final class OfficialReportRenderer
{
    public function __construct(
        private readonly OfficialReportSnapshotService $snapshots,
        private readonly OfficeOpenXmlExporter $office,
    ) {}

    /** @return array{path: string, extension: string, mime: string, size: int, hash: string} */
    public function render(OfficialReportSnapshot $snapshot, string $format): array
    {
        if (! in_array($format, ReportExportJob::FORMATS, true)) {
            throw new RuntimeException("Unsupported official report export format: {$format}");
        }
        $this->snapshots->assertIntegrity($snapshot);
        $payload = $this->canonicalPayload($snapshot);
        $columns = collect($payload['columns'] ?? []);
        $headers = $columns->pluck('label')->map(fn (mixed $value): string => (string) $value)->all();
        $rows = collect($payload['rows'] ?? [])->map(fn (array $row): array => $columns
            ->map(fn (array $column): mixed => data_get($row, $column['key']))->all());
        $title = (string) ($payload['report_title'] ?? $snapshot->report_type);
        $snapshot->loadMissing('approver');
        $metadata = [
            (string) __('official_reports.fields.number') => $snapshot->report_number,
            (string) __('official_reports.fields.revision') => $snapshot->revision,
            (string) __('official_reports.fields.approver') => $snapshot->approver?->name ?: '—',
            'SHA-256' => $snapshot->source_hash,
        ];

        $path = match ($format) {
            'csv' => $this->csv($headers, $rows, $metadata),
            'pdf' => $this->pdf($snapshot, $payload, $title),
            'xlsx' => $this->office->xlsx($title, $headers, $rows),
            'docx' => $this->office->docx($title, $headers, $rows, $snapshot->filters, $metadata),
        };
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        if ($size === false || $hash === false || $size < 1) {
            @unlink($path);
            throw new RuntimeException('The official report renderer produced an invalid file.');
        }

        return [
            'path' => $path,
            'extension' => $format,
            'mime' => match ($format) {
                'csv' => 'text/csv; charset=UTF-8',
                'pdf' => 'application/pdf',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            },
            'size' => $size,
            'hash' => $hash,
        ];
    }

    /** @param list<string> $headers */
    private function csv(array $headers, iterable $rows, array $metadata): string
    {
        $path = $this->temporaryPath('csv');
        $output = fopen($path, 'wb');
        if ($output === false) {
            throw new RuntimeException('Unable to open the official CSV export.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        foreach ($metadata as $key => $value) {
            Csv::writeRow($output, [(string) $key, $value]);
        }
        Csv::writeRow($output, []);
        Csv::writeRow($output, $headers);
        foreach ($rows as $row) {
            Csv::writeRow($output, $row);
        }
        fclose($output);

        return $path;
    }

    /** @param array<string, mixed> $payload */
    private function pdf(OfficialReportSnapshot $snapshot, array $payload, string $title): string
    {
        $path = $this->temporaryPath('pdf');
        $report = [
            'activeReport' => $snapshot->report_type,
            'filters' => $snapshot->filters,
            'metrics' => $payload['metrics'] ?? [],
            'columns' => $payload['columns'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'breakdowns' => $payload['breakdowns'] ?? [],
        ];
        $bytes = Pdf::loadView('librarian.reports.document', array_merge($report, [
            'report' => $report,
            'reportTitle' => $title,
            'generatedAt' => $snapshot->approved_at ?? $snapshot->created_at,
            'reportNumber' => $snapshot->report_number,
            'reportRevision' => $snapshot->revision,
            'approverName' => $snapshot->approver?->name,
            'printMode' => false,
        ]))->setPaper('a4', 'landscape')->output();
        if (file_put_contents($path, $bytes) === false) {
            @unlink($path);
            throw new RuntimeException('Unable to write the official PDF export.');
        }

        return $path;
    }

    private function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'official-report-');
        if ($base === false) {
            throw new RuntimeException('Unable to allocate an official report export file.');
        }
        $path = $base.'.'.$extension;
        @unlink($base);

        return $path;
    }

    /** @return array<string, mixed> */
    private function canonicalPayload(OfficialReportSnapshot $snapshot): array
    {
        $stream = Storage::disk($snapshot->archive_disk)->readStream($snapshot->archive_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open the canonical official report source.');
        }
        $json = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($json)) {
            throw new RuntimeException('Unable to read the canonical official report source.');
        }
        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The canonical official report source is invalid JSON.', previous: $exception);
        }
        if (! is_array($payload)
            || ! hash_equals($snapshot->source_hash, OfficialReportSnapshot::hashPayload($payload))) {
            throw new RuntimeException('The canonical official report source does not match its signed payload.');
        }

        return $payload;
    }
}
