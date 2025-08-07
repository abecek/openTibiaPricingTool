<?php
declare(strict_types=1);

namespace App\Pricing;

use App\MonsterLoot\DTO\MonsterLoot;
use App\SpawnAnalyzer\DTO\MonsterCount;

readonly class PriceSuggestionEngine
{
    /**
     * @param MonsterCount[] $spawnData
     * @param array<int, array<string, string>> $csvItems
     * @param array<string, array<string, MonsterLoot>> $lootData
     * @return array<string, array<string, array{buy: int, sell: int}>>
     */
    public function suggestPrices(array $spawnData, array $csvItems, array $lootData): array
    {
        $cityMonsterCounts = [];
        foreach ($spawnData as $entry) {
            $city = $entry->getCity();
            $monster = $entry->getMonster();
            $count = $entry->getCount();
            $cityMonsterCounts[$city][$monster] = $count;
        }

        $result = [];

        foreach ($csvItems as $row) {
            $itemName = strtolower(trim($row['name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $result[$itemName] = [];

            foreach ($cityMonsterCounts as $city => $monsters) {
                $totalChance = 0;
                $lootOccurrences = 0;

                foreach ($monsters as $monster => $count) {
                    if (!isset($lootData[$city][$monster])) {
                        continue;
                    }

                    foreach ($lootData[$city][$monster]->getItems() as $lootItem) {
                        if (strtolower($lootItem->getName()) === $itemName) {
                            $chance = $lootItem->getChance(); // drop chance
                            $totalChance += $chance * $count;
                            $lootOccurrences++;
                        }
                    }
                }

                $baseBuy = $this->parsePriceField($row['Tibia Buy Price'] ?? null);
                $baseSell = $this->parsePriceField($row['Tibia Sell Price'] ?? null);
                if ($lootOccurrences > 0) {
                    // base, example heuristic
                    $factor = min(1.0, $totalChance / 100000); // bigger change, lower buy price / higher sell price

                    if ($baseBuy !== null) {
                        $suggestedBuy = (int) round($baseBuy * (1.0 - 0.3 * $factor));
                        $suggestedBuy = $baseBuy !== null ? $this->roundPrice((int) round($suggestedBuy)) : null;
                    } else {
                        $suggestedBuy = $baseBuy;
                    }

                    if ($baseSell !== null) {
                        $suggestedSell = (int) round($baseSell * (1.0 + 0.5 * $factor));
                        $suggestedSell = $baseSell !== null ? $this->roundPrice((int) round($suggestedSell)) : null;
                    } else {
                        $suggestedSell = $baseSell;
                    }

                    $result[$itemName][$city] = [
                        'buy' => $suggestedBuy,
                        'sell' => $suggestedSell,
                    ];
                } else {
                    // does not exist - use base tibia prices
                    $result[$itemName][$city] = [
                        'buy' => $baseBuy,
                        'sell' => $baseSell,
                    ];
                }


            }
        }

        return $this->adjustBuyPricesAgainstSellAcrossCities($result);
    }

    /**
     * Parses a Tibia price field that may contain a number or a range like "4-5".
     *
     * @param string|null $value
     * @return int|null
     */
    private function parsePriceField(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = str_replace(' ', '', $value);

        if (is_numeric($value)) {
            return (int)$value;
        }

        if (strpos($value, '-') !== false) {
            $parts = explode('-', $value);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                return (int) round((($parts[0] + $parts[1]) / 2));
            }
        }

        return null;
    }

    /**
     * @param int $price
     * @return int
     */
    private function roundPrice(int $price): int
    {
        if ($price < 100) {
            return (int) (round($price / 5) * 5);
        } elseif ($price < 1000) {
            return (int) (round($price / 10) * 10);
        } elseif ($price < 10000) {
            return (int) (round($price / 50) * 50);
        } else {
            return (int) (round($price / 100) * 100);
        }
    }

    /**
     * Ensures that buy prices are not lower than any sell price across cities for the same item.
     *
     * @param array<string, array<string, array{buy: int|null, sell: int|null}>> $prices
     * @return array<string, array<string, array{buy: int|null, sell: int|null}>>
     */
    private function adjustBuyPricesAgainstSellAcrossCities(array $prices): array
    {
        foreach ($prices as $itemName => $cityPrices) {
            $maxSell = null;

            foreach ($cityPrices as $city => $entry) {
                if (isset($entry['sell']) && is_numeric($entry['sell'])) {
                    $maxSell = max($maxSell ?? 0, $entry['sell']);
                }
            }

            if ($maxSell !== null) {
                foreach ($cityPrices as $city => $entry) {
                    if (isset($entry['buy']) && is_numeric($entry['buy']) && $entry['buy'] < $maxSell) {
                        $prices[$itemName][$city]['buy'] = $this->roundPrice($maxSell);
                    }
                }
            }
        }

        return $prices;
    }
}
