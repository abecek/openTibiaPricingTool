<?php
declare(strict_types=1);

namespace App\Pricing;

use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\DTO\MonsterLoot;
use App\SpawnAnalyzer\DTO\MonsterCount;

readonly class PriceSuggestionEngine
{
    /**
     * @param TibiaPriceProvider $priceProvider
     */
    public function __construct(
        private TibiaPriceProvider $priceProvider
    ) {
    }

    /**
     * @param MonsterCount[] $spawnData
     * @param array<string, array<string, MonsterLoot>> $lootData
     * @return array<string, array<string, array{buy: int, sell: int}>>
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

            $loot = $lootData[$city][$monster];

            foreach ($loot->getAllItemsRecursive() as $item) {
                if (!$item instanceof LootItem || !$item->getId()) {
                    continue;
                }

                $id = $item->getId();
                $itemName = $item->getName() ?? "Item #{$id}";

                $chance = $item->getChance();
                $countMax = $item->getCountMax() ?? 1;
                $baseValue = intval(($chance / 100000) * $countMax * $count);

                $official = $this->priceProvider->getById($id);
                if ($official && $official['sell']) {
                    $baseValue = min($baseValue, $official['sell']);
                }

                $result[$itemName][$city]['buy'] = ($result[$itemName][$city]['buy'] ?? 0) + $baseValue;
                $result[$itemName][$city]['sell'] = ($result[$itemName][$city]['sell'] ?? 0) + (int) round($baseValue * 0.5);
            }
        }

        return $result;
    }
}
