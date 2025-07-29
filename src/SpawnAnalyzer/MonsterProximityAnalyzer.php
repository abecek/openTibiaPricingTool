<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer;

use App\SpawnAnalyzer\DTO\MonsterCount;

class MonsterProximityAnalyzer
{
    /**
     * @param array $spawnEntries
     * @param array $cities
     * @param int $radius
     * @return array
     */
    public function analyze(array $spawnEntries, array $cities, int $radius): array
    {
        $counts = []; // cityName => [monsterName => count]
        foreach ($cities as $city) {
            $counts[$city->getName()] = [];
        }
        foreach ($spawnEntries as $s) {
            foreach ($cities as $city) {
//                if ($s->getZ() !== $city->getZ()) {
//                    continue;
//                }
                $dx = $s->getX() - $city->getX();
                $dy = $s->getY() - $city->getY();
                if (($dx*$dx + $dy*$dy) <= ($radius * $radius)) {
                    $counts[$city->getName()][$s->getMonster()] =
                        ($counts[$city->getName()][$s->getMonster()] ?? 0) + 1;
                    break;
                }
            }
        }
        $result = [];
        foreach ($counts as $cityName => $monsters) {
            foreach ($monsters as $m => $cnt) {
                $result[] = new MonsterCount($cityName, $m, $cnt);
            }
        }
        return $result;
    }
}
