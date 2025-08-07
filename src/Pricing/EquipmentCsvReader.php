<?php
declare(strict_types=1);

namespace App\Pricing;
use RuntimeException;

class EquipmentCsvReader
{
    /**
     * @param string $pathToCsv
     * @return array<int, array<string, string>>
     */
    public function read(string $pathToCsv): array
    {
        if (!file_exists($pathToCsv)) {
            throw new RuntimeException("CSV file not found: " . $pathToCsv);
        }

        $handle = fopen($pathToCsv, 'r');
        if (!$handle) {
            throw new RuntimeException("Unable to open CSV file: " . $pathToCsv);
        }

        $data = [];
        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false) {
            fclose($handle);
            throw new RuntimeException("CSV file is empty or invalid: " . $pathToCsv);
        }

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = $row[$i] ?? '';
            }
            $data[] = $assoc;
        }

        fclose($handle);
        return $data;
    }
}
