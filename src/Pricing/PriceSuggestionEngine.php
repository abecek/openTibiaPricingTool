<?php
declare(strict_types=1);

namespace App\Pricing;

use App\MonsterLoot\DTO\MonsterLoot;
use App\SpawnAnalyzer\DTO\MonsterCount;
use Psr\Log\LoggerInterface;

readonly class PriceSuggestionEngine
{
    private const array EXCLUDED_NPCS = ['Rashid'];

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

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

            // BEFORE city loop (for current CSV row):
            $buyPerCityJson  = $this->parseCityPriceJson($row['Tibia Buy Price']  ?? null);
            $sellPerCityJson = $this->parseCityPriceJson($row['Tibia Sell Price'] ?? null);

            $scalarBaseBuy  = $this->parsePriceFieldFlexible($row['Tibia Buy Price'] ?? null);
            $scalarBaseSell = $this->parsePriceFieldFlexible($row['Tibia Sell Price'] ?? null);
            foreach ($cityMonsterCounts as $city => $monsters) {
                // resolve baseline per city using JSON->dominant with scalar fallback
                $baseBuy  = $this->getBaselinePriceForCity($buyPerCityJson,  $city, 'buy',  $scalarBaseBuy);
                $baseSell = $this->getBaselinePriceForCity($sellPerCityJson, $city, 'sell', $scalarBaseSell);

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

                if ($lootOccurrences > 0) {
                    $factor = min(1.0, $totalChance / 100000);

                    $suggestedBuy = $baseBuy !== null
                        ? $this->roundPrice((int) round($baseBuy * (1.0 - 0.3 * $factor)))
                        : null;

                    $suggestedSell = $baseSell !== null
                        ? $this->roundPrice((int) round($baseSell * (1.0 + 0.5 * $factor)))
                        : null;

                    $result[$itemName][$city] = [
                        'buy'  => $suggestedBuy,
                        'sell' => $suggestedSell,
                    ];
                } else {
                    // no local loot → keep baseline as-is (including nulls if unknown)
                    $result[$itemName][$city] = [
                        'buy'  => $baseBuy,
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
     * @param string|null $value
     * @return int|null
     */
    private function parsePriceFieldFlexible(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Try decode JSON of format {"Thais":[85,85], "Carlin":[85]}
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            // Flatten all prices into one array
            $allPrices = [];
            foreach ($decoded as $prices) {
                if (is_array($prices)) {
                    foreach ($prices as $npcName => $v) {
                        if (in_array($npcName, self::EXCLUDED_NPCS)) {
                            $this->logger->debug(
                                sprintf('Unset price from/for npc: %s', $npcName)
                            );
                            unset($prices[$npcName]);
                        }
                    }

                    $allPrices = array_merge($allPrices, $prices);
                }
            }
            if (!empty($allPrices)) {
                // Pick the most frequent price
                $counts = array_count_values($allPrices);
                arsort($counts);
                return (int) array_key_first($counts);
            }
            return null;
        }

        // Fallback: old parsing logic
        return $this->parsePriceField($value);
    }

    /**
     * Parse per-city prices from JSON column: {"Carlin":[240,240], "Thais":[240,25], ...}
     *
     * @param string|null $json
     * @return array<string, int[]>  Map city => list of prices
     */
    private function parseCityPriceJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $city => $values) {
            if (!is_array($values)) {
                continue;
            }
            // keep only numeric ints
            $intVals = [];
            foreach ($values as $npcName => $v) {
                if (in_array($npcName, self::EXCLUDED_NPCS)) {
                    $this->logger->debug(
                        sprintf('Skipped price from/for npc: %s', $npcName)
                    );
                    continue;
                }
                if (is_numeric($v)) {
                    $intVals[] = (int)$v;
                }
            }
            if ($intVals) {
                $result[(string)$city] = $intVals;
            }
        }
        return $result;
    }

    /**
     * Pick a single baseline price from a list using:
     *  - mode (most frequent),
     *  - if tie: median of the full list,
     *  - if still ambiguous: tie-break (lower for "sell", higher for "buy").
     *
     * @param int[] $values
     * @param 'buy'|'sell' $type
     * @return int|null
     */
    private function chooseDominantPrice(array $values, string $type): ?int
    {
        if (empty($values)) {
            return null;
        }

        // Mode
        $freq = array_count_values($values);
        arsort($freq); // by count desc, then by value asc
        $topCount = reset($freq);
        $candidates = array_keys(array_filter($freq, fn($c) => $c === $topCount)); // price candidates

        if (count($candidates) === 1) {
            return (int)$candidates[0];
        }

        // Median of full set
        sort($values);
        $n = count($values);
        $median = ($n % 2)
            ? $values[(int) floor($n / 2)]
            : (int) round(($values[$n/2 - 1] + $values[$n/2]) / 2);

        if (in_array($median, $candidates, true)) {
            return (int)$median;
        }

        // Final tie-break:
        // - For SELL we prefer the LOWER candidate (more conservative payout to player)
        // - For BUY  we prefer the HIGHER candidate (more conservative cost)
        return $type === 'sell'
            ? (int) min($candidates)
            : (int) max($candidates);
    }

    /**
     * Resolve baseline price for a given city:
     *  - Prefer per-city list (JSON) -> chooseDominantPrice(...)
     *  - Fallback to scalar Tibia price (parsed by parsePriceField)
     *
     * @param array<string,int[]> $perCityPrices
     * @param string $city
     * @param 'buy'|'sell' $type
     * @param int|null $scalarFallback
     * @return int|null
     */
    private function getBaselinePriceForCity(array $perCityPrices, string $city, string $type, ?int $scalarFallback): ?int
    {
        if (isset($perCityPrices[$city]) && is_array($perCityPrices[$city]) && !empty($perCityPrices[$city])) {
            $picked = $this->chooseDominantPrice($perCityPrices[$city], $type);
            if ($picked !== null) {
                return $picked;
            }
        }
        return $scalarFallback;
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
