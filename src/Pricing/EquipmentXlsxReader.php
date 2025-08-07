<?php
declare(strict_types=1);

namespace App\Pricing;

use PhpOffice\PhpSpreadsheet\IOFactory;

class EquipmentXlsxReader
{
    /**
     * @param string $pathToXlsx
     * @return array<int, array<string, string>>
     */
    public function read(string $pathToXlsx): array
    {
        if (!file_exists($pathToXlsx)) {
            throw new \RuntimeException("XLSX file not found: " . $pathToXlsx);
        }

        $spreadsheet = IOFactory::load($pathToXlsx);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map('trim', array_values($rows[1]));
        unset($rows[1]); // usuń nagłówki

        $data = [];
        foreach ($rows as $row) {
            $assoc = [];
            $values = array_values($row);
            foreach ($headers as $i => $key) {
                $assoc[$key] = $values[$i] ?? '';
            }
            $data[] = $assoc;
        }

        return $data;
    }
}
