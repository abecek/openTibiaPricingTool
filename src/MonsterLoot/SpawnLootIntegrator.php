<?php
declare(strict_types=1);

namespace App\MonsterLoot;

use App\MonsterLoot\DTO\MonsterLoot;
use App\SpawnAnalyzer\DTO\MonsterCount;

class SpawnLootIntegrator
{
    /**
     * @param MonsterDataProvider $provider
     * @param MonsterCount[] $spawnData
     * @return array<string, array<string, MonsterLoot>> [cityName => [monsterName => MonsterLoot]]
     */
    public function integrate(MonsterDataProvider $provider, array $spawnData): array
    {
        $result = [];

        foreach ($spawnData as $entry) {
            $city = $entry->getCity();
            $monster = $entry->getMonster();

            if (!isset($result[$city])) {
                $result[$city] = [];
            }

            if (!isset($result[$city][$monster])) {
                $loot = $provider->getLoot($monster);
                if ($loot !== null) {
                    $result[$city][$monster] = $loot;
                }
            }
        }

        return $result;
    }
}
