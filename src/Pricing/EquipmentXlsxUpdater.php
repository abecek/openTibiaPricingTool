<?php
declare(strict_types=1);

namespace App\Pricing;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class EquipmentXlsxUpdater
{
    /**
     * Update only the Buy and Sell columns in an existing XLSX file, preserving images and formatting,
     * and auto-size all columns based on content length.
     *
     * @param string $pathToXlsx  Path to existing XLSX file.
     * @param array<string, array<string, array{buy: int|null, sell: int|null}>> $suggestions  Suggested prices per city.
     * @return void
     */
    public function updateBuySellColumns(string $pathToXlsx, array $suggestions): void
    {
        if (!file_exists($pathToXlsx)) {
            throw new RuntimeException("XLSX file not found: {$pathToXlsx}");
        }

        $spreadsheet = IOFactory::load($pathToXlsx);
        $sheet = $spreadsheet->getActiveSheet();

        // Read headers from the first row
        $headers = [];
        foreach ($sheet->getColumnIterator() as $col) {
            $colIndex = $col->getColumnIndex();
            $headers[$colIndex] = trim((string) $sheet->getCell($colIndex . '1')->getValue());
        }

        // Locate required columns
        $buyColLetter  = array_search('Buy', $headers, true);
        $sellColLetter = array_search('Sell', $headers, true);
        $nameColLetter = array_search('name', array_map('strtolower', $headers), true);

        if (!$buyColLetter || !$sellColLetter || !$nameColLetter) {
            throw new RuntimeException("Required columns (Buy, Sell, name) not found in XLSX.");
        }

        $highestRow = $sheet->getHighestRow();

        // Update Buy/Sell cells
        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $itemName = strtolower(trim((string) $sheet->getCell($nameColLetter . $rowIndex)->getValue()));
            if ($itemName === '' || !isset($suggestions[$itemName])) {
                continue;
            }

            $buyPerCity = [];
            $sellPerCity = [];
            foreach ($suggestions[$itemName] as $city => $prices) {
                $buyPerCity[$city]  = $prices['buy'];
                $sellPerCity[$city] = $prices['sell'];
            }

            $sheet->setCellValue($buyColLetter . $rowIndex, json_encode($buyPerCity, JSON_UNESCAPED_UNICODE));
            $sheet->setCellValue($sellColLetter . $rowIndex, json_encode($sellPerCity, JSON_UNESCAPED_UNICODE));
        }

        // Auto-size all columns based on content
        foreach ($sheet->getColumnIterator() as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }

        // Save file with changes
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($pathToXlsx);
    }
}
