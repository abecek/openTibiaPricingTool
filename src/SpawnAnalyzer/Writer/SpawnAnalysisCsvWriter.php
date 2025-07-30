<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\Writer;

use App\SpawnAnalyzer\DTO\MonsterCount;
use RuntimeException;

class SpawnAnalysisCsvWriter
{
    /**
     * @param MonsterCount[] $results
     * @param string $path
     * @return void
     */
    public function write(array $results, string $path): void
    {
        $handle = fopen($path, 'w');
        if (!$handle) {
            throw new RuntimeException("Failed to open file for writing: $path");
        }

        // Header
        fputcsv($handle, ['City', 'Radius', 'Monster', 'Count'], ';');

        foreach ($results as $entry) {
            fputcsv($handle, [
                $entry->getCity(),
                $entry->getRadius(),
                $entry->getMonster(),
                $entry->getCount()
            ], ';');
        }

        fclose($handle);
    }
}
