<?php
declare(strict_types=1);

namespace App\Pricing;

use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\DTO\MonsterLoot;
use App\SpawnAnalyzer\DTO\MonsterCount;

readonly class PriceSuggestionEngine
{
    /**
     * @param MonsterCount[] $spawnData
     * @param array<string, array<string, MonsterLoot>> $lootData [city => [monster => MonsterLoot]]
     * @return array<string, array<string, array{buy: int, sell: int}>> [itemName => [city => ['buy' => x, 'sell' => y]]]
     */
    public function suggestPrices(array $spawnData, array $lootData): array
    {
        $result = [];

        foreach ($spawnData as $entry) {
            $city = $entry->getCity();
            $monster = $entry->getMonster();
            $count = $entry->getCount();

            if (!isset($lootData[$city][$monster])) {
                continue;
            }

            /** @var MonsterLoot $loot */
            $loot = $lootData[$city][$monster];

            foreach ($loot->getAllItemsRecursive() as $item) {
                if (!$item instanceof LootItem) {
                    continue;
                }

                $itemName = $item->getName();
                if (!$itemName) {
                    continue; // Skip if item name is still missing
                }

                // Basic heuristic: price = (chance * countMax) * spawnCount * K
                $chance = $item->getChance();
                $countMax = $item->getCountMax() ?? 1;
                $baseValue = intval(($chance / 100000) * $countMax * $count);

                if (!isset($result[$itemName][$city])) {
                    $result[$itemName][$city] = ['buy' => 0, 'sell' => 0];
                }

                $result[$itemName][$city]['buy'] += $baseValue;
                $result[$itemName][$city]['sell'] += (int) round($baseValue * 0.5);
            }
        }

        return $result;
    }
}
