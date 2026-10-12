<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class ExportService
{
    /**
     * Export data as CSV and return download response.
     *
     * @param Collection|array $data
     * @param array $headers
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv($data, $headers, $filename)
    {
        $filename = $this->sanitizeFilename($filename) . '.csv';

        return response()->streamDownload(function () use ($data, $headers) {
            $file = fopen('php://output', 'w');

            // Write BOM for UTF-8 (helps with Excel)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            $delimiter = $this->csvDelimiter();

            // Write headers
            fputcsv($file, $headers, $delimiter);

            // Write data rows
            foreach ($data as $row) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $value = is_array($row) ? ($row[$header] ?? '') : ($row->{$header} ?? '');
                    $csvRow[] = $this->formatCsvValue($value);
                }
                fputcsv($file, $csvRow, $delimiter);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export data as Excel and return download response.
     *
     * @param Collection|array $data
     * @param array $headers
     * @param string $filename
     * @param string|null $title
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportExcel($data, $headers, $filename, $title = null)
    {
        $filename = $this->sanitizeFilename($filename) . '.xlsx';

        // Convert data to array format
        $exportData = [];
        foreach ($data as $row) {
            $rowData = [];
            foreach ($headers as $header) {
                $value = is_array($row) ? ($row[$header] ?? '') : ($row->{$header} ?? '');
                $rowData[] = $this->formatValue($value);
            }
            $exportData[] = $rowData;
        }

        // Create Excel export using a proper class
        $exportClass = new class($exportData, $headers, $title) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\WithColumnWidths
        {
            protected $data;
            protected $headings;
            protected $title;

            public function __construct($data, $headings, $title = null)
            {
                $this->data = $data;
                $this->headings = $headings;
                $this->title = $title ?? 'Sheet1';
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                return $this->title;
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']]],
                ];
            }

            public function columnWidths(): array
            {
                $widths = [];
                for ($i = 0; $i < count($this->headings); $i++) {
                    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $widths[$column] = 15;
                }
                return $widths;
            }
        };

        return Excel::download($exportClass, $filename);
    }

    /**
     * Export data as PDF and return download response.
     *
     * @param Collection|array $data
     * @param array $headers
     * @param string $filename
     * @param string|null $title
     * @param array $filters
     * @return \Illuminate\Http\Response
     */
    public function exportPdf($data, $headers, $filename, $title = null, $filters = [])
    {
        $filename = $this->sanitizeFilename($filename) . '.pdf';

        // Convert data to array format
        $exportData = [];
        foreach ($data as $row) {
            $rowData = [];
            foreach ($headers as $header) {
                $value = is_array($row) ? ($row[$header] ?? '') : ($row->{$header} ?? '');
                $rowData[] = $this->formatValue($value);
            }
            $exportData[] = $rowData;
        }

        $html = view('exports.pdf-table', [
            'title' => $title ?? __('common.export'),
            'headers' => $headers,
            'data' => $exportData,
            'filters' => $filters,
            'exportDate' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Format value for export (handle dates, numbers, etc.).
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue($value)
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? __('common.yes') : __('common.no');
        }

        return (string) $value;
    }

    /**
     * CSV separator for the current language: French Excel (Burkina Faso) only splits
     * columns on ";" and reads "," as the decimal mark.
     */
    public function csvDelimiter(): string
    {
        return app()->getLocale() === 'fr' ? ';' : ',';
    }

    /** formatValue + decimal comma for numbers when the CSV uses ";". */
    public function formatCsvValue($value): string
    {
        if ((is_float($value) || is_int($value)) && $this->csvDelimiter() === ';') {
            return str_replace('.', ',', (string) $value);
        }

        return $this->formatValue($value);
    }

    /**
     * Translated label of a stored code (status, kind, gender…): first existing
     * common.<prefix><value> key, else the raw value.
     */
    public function valueLabel(?string $value, string ...$prefixes): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        foreach ($prefixes as $prefix) {
            $key = 'common.'.$prefix.strtolower($value);
            if (__($key) !== $key) {
                return __($key);
            }
        }

        return $value;
    }

    /**
     * Sanitize filename to remove invalid characters.
     *
     * @param string $filename
     * @return string
     */
    protected function sanitizeFilename($filename)
    {
        // Remove invalid characters
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        // Remove leading/trailing underscores
        $filename = trim($filename, '_');
        
        return $filename ?: 'export';
    }

    /**
     * Generate filename with filters and timestamp.
     *
     * @param string $resource
     * @param array $filters
     * @return string
     */
    public function generateFilename($resource, $filters = [])
    {
        $parts = [$resource];

        // Add filter parts
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $parts[] = $filters['start_date'] . '_to_' . $filters['end_date'];
        } elseif (!empty($filters['start_date'])) {
            $parts[] = 'from_' . $filters['start_date'];
        } elseif (!empty($filters['end_date'])) {
            $parts[] = 'to_' . $filters['end_date'];
        }

        if (!empty($filters['status'])) {
            $parts[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $parts[] = 'search';
        }

        // Add timestamp
        $parts[] = now()->format('Ymd_His');

        return implode('_', $parts);
    }
}

