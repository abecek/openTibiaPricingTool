<?php
declare(strict_types=1);

namespace App\Scrapper;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OutputWriter
{
    /**
     * @param string $format
     */
    public function __construct(
        private readonly string $format = 'csv'
    ) {
    }

    /**
     * Writes the item data to either a CSV or XLSX file.
     *
     * @param array $items
     * @param string $outputPath
     * @return void
     */
    public function write(array $items, string $outputPath): void
    {
        if (empty($items)) {
            throw new \RuntimeException("No data to write.");
        }

        if ($this->format === 'xlsx') {
            $this->writeXlsx($items, $outputPath);
        } else {
            $this->writeCsv($items, $outputPath);
        }
    }

    /**
     * Write output to a CSV file.
     */
    private function writeCsv(array $items, string $outputPath): void
    {
        $fp = fopen($outputPath, 'w');

        if (!$fp) {
            throw new \RuntimeException("Unable to open file for writing: " . $outputPath);
        }

        // Write headers
        fputcsv($fp, array_keys($items[0]), ';');

        foreach ($items as $row) {
            fputcsv($fp, $row, ';');
        }

        fclose($fp);
    }

    /**
     * Write output to an XLSX file with optional item images.
     */
    private function writeXlsx(array $items, string $outputPath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Write headers
        $headers = array_keys($items[0]);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getRowDimension(1)->setRowHeight(20);

        foreach ($items as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +2 because headers are in row 1
            $sheet->getRowDimension($rowNumber)->setRowHeight(36);
            $colIndex = 0;

            foreach ($headers as $key) {
                $cell = $this->columnLetter($colIndex) . $rowNumber;

                if ($key === 'Image' && is_string($row[$key] ?? '') && file_exists($row[$key])) {
                    $drawing = new Drawing();
                    $drawing->setPath($row[$key]);
                    $drawing->setCoordinates($cell);
                    $drawing->setHeight(32);
                    $drawing->setWorksheet($sheet);
                } else {
                    $sheet->setCellValue($cell, $row[$key] ?? '');
                }

                $colIndex++;
            }
        }

        $columnCount = count($headers);
        for ($i = 0; $i < $columnCount; $i++) {
            $colLetter = $this->columnLetter($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Apply auto-filter to first row (header)
        $sheet->setAutoFilter(
            $sheet->calculateWorksheetDimension()
        );

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }

    /**
     * Converts a zero-based column index to Excel-style column letters (e.g. 0 => A, 27 => AB)
     */
    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }

}