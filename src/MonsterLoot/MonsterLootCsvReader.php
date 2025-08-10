<?php
declare(strict_types=1);

namespace App\MonsterLoot;

use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\DTO\MonsterLoot;
use RuntimeException;

class MonsterLootCsvReader
{
    /**
     * @param string $path
     * @return array<string, array<string, MonsterLoot>> [city => [monster => MonsterLoot]]
     */
    public function read(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("File not found: $path");
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open file: $path");
        }

        $header = fgetcsv($handle, 0, ';');
        $results = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            [$city, $monster, $itemName, $itemId, $chance, $countMax] = $row;

            $item = new LootItem(
                trim($itemName, '"'),
                (int)$itemId,
                (int)$chance,
                $countMax !== 'n/a' ? (int)$countMax : null
            );

            if (!isset($results[$city][$monster])) {
                $results[$city][$monster] = new MonsterLoot($monster, []);
            }

            $results[$city][$monster]->addItem($item);
        }

        fclose($handle);
        return $results;
    }
}
