<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer;

use App\SpawnAnalyzer\DTO\MonsterCount;
use RuntimeException;

class SpawnCsvReader
{
    /**
     * @param string $csvPath
     * @return MonsterCount[]
     */
    public function read(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new RuntimeException("CSV file not found: $csvPath");
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            throw new RuntimeException("Failed to open CSV file: $csvPath");
        }

        $header = fgetcsv($handle, 0, ';');
        $expected = ['City', 'Radius', 'Monster', 'Count'];
        if ($header !== $expected) {
            throw new RuntimeException("Invalid CSV header, expected: " . implode(',', $expected));
        }

        $result = [];
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            [$city, $radius, $monster, $count] = $data;
            $result[] = new MonsterCount(
                $city,
                (int)$radius,
                trim($monster, ' "'),
                (int)$count
            );
        }

        fclose($handle);
        return $result;
    }
}
