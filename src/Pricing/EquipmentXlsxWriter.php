<?php
declare(strict_types=1);

namespace App\Pricing;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use RuntimeException;

class EquipmentXlsxWriter
{
    /**
     * Writes equipment data to an XLSX file with images and formatting.
     *
     * @param string $pathToXlsx Output XLSX file path
     * @param array<int, array<string, string>> $rows Data rows to write
     * @return void
     */
    public function write(string $pathToXlsx, array $rows): void
    {
        if (empty($rows)) {
            throw new RuntimeException("No data to write to XLSX.");
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Write headers
        $headers = array_keys($rows[0]);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getRowDimension(1)->setRowHeight(20);

        // Write data rows
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2; // Data starts from row 2
            $sheet->getRowDimension($excelRow)->setRowHeight(36);

            foreach ($headers as $colIndex => $header) {
                $cell = $this->columnLetter($colIndex) . $excelRow;

                // Insert image if column is "Image" and file exists
                if ($header === 'Image' && !empty($row[$header])) {
                    $imagePath = $this->resolveImagePath($row[$header]);
                    if ($imagePath !== null) {
                        $drawing = new Drawing();
                        $drawing->setPath($imagePath);
                        $drawing->setCoordinates($cell);
                        $drawing->setHeight(32);
                        $drawing->setWorksheet($sheet);
                    }
                } else {
                    // Write text value
                    $sheet->setCellValue($cell, $row[$header] ?? '');
                }
            }
        }

        // Auto-size all columns
        $columnCount = count($headers);
        for ($i = 0; $i < $columnCount; $i++) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }

        // Apply auto-filter to the header row
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        // Save XLSX file
        $writer = new Xlsx($spreadsheet);
        $writer->save($pathToXlsx);
    }

    /**
     * Converts a zero-based column index to Excel column letters (A, B, C, ..., AA, AB, ...)
     *
     * @param int $index Column index
     * @return string Excel column letter
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

    /**
     * Resolves the full path to an image file stored in the "images" directory.
     *
     * @param string $imageFile Image filename
     * @return string|null Full file path or null if file does not exist
     */
    private function resolveImagePath(string $imageFile): ?string
    {
        $baseDir = __DIR__ . '/../../images/';
        $path = $baseDir . $imageFile;
        if (file_exists($path)) {
            return $path;
        }
        return null;
    }
}
