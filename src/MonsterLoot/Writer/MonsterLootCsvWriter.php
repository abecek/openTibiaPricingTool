<?php

declare(strict_types=1);

namespace App\MonsterLoot\Writer;

use App\Item\ItemLookupService;
use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\DTO\MonsterLoot;
use RuntimeException;

readonly class MonsterLootCsvWriter
{
    /**
     * @param ItemLookupService $itemLookup
     */
    public function __construct(
        private ItemLookupService $itemLookup
    ) {
    }

    /**
     * @param array<string, array<string, MonsterLoot>> $data
     * @param string $path
     * @return void
     */
    public function write(array $data, string $path): void
    {
        $handle = fopen($path, 'w');
        if (!$handle) {
            throw new RuntimeException("Failed to open file for writing: $path");
        }

        // Header
        fputcsv($handle, ['City', 'Monster', 'Item Name', 'Item ID', 'Drop Chance', 'Max Count'], ';');

        foreach ($data as $city => $monsters) {
            foreach ($monsters as $monster => $loot) {
                foreach ($loot->getItems() as $item) {
                    $this->writeLootItem($handle, $city, $monster, $item);
                }
            }
        }

        fclose($handle);
    }

    /**
     * @param $handle
     * @param string $city
     * @param string $monster
     * @param LootItem $item
     * @return void
     */
    private function writeLootItem($handle, string $city, string $monster, LootItem $item): void
    {
        $name = $item->getName();
        $id = $item->getId();

        if (!$name && $id !== null) {
            $name = $this->itemLookup->getNameById($id);
        }
        if (!$id && $name !== null) {
            $id = $this->itemLookup->getIdByName($name);
        }

        fputcsv($handle, [
            $city,
            $monster,
            $name ?? 'unknown',
            $id ?? 'unknown',
            $item->getChance(),
            $item->getCountMax() ?? 'n/a',
        ], ';');

        foreach ($item->getInside() as $nested) {
            $this->writeLootItem($handle, $city, $monster, $nested);
        }
    }
}
