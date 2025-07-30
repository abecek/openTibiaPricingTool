<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer;

use App\SpawnAnalyzer\DTO\City;
use App\SpawnAnalyzer\DTO\MonsterCount;
use App\SpawnAnalyzer\DTO\SpawnEntry;

class MonsterProximityAnalyzer
{
    /**
     * @param array $spawnEntries
     * @param array $cities
     * @return array
     */
    public function analyze(array $spawnEntries, array $cities): array
    {
        $results = []; // cityName => [monsterName => count]
        foreach ($cities as $city) {
            $results[$city->getName()] = [];
        }
        $radiuses = [];
        /** @var SpawnEntry $s */
        foreach ($spawnEntries as $s) {
            /** @var City $city */
            foreach ($cities as $city) {
                $radius = $city->getRadius();
                $radiuses[$city->getName()] = $radius;
//                if ($s->getZ() !== $city->getZ()) {
//                    continue;
//                }
                $dx = $s->getX() - $city->getX();
                $dy = $s->getY() - $city->getY();
                if (($dx*$dx + $dy*$dy) <= ($radius * $radius)) {
                    $results[$city->getName()][$s->getMonster()] =
                        ($results[$city->getName()][$s->getMonster()] ?? 0) + 1;
                    break;
                }
            }
        }
        $result = [];
        foreach ($results as $cityName => $monsters) {
            foreach ($monsters as $m => $cnt) {
                $result[] = new MonsterCount(
                    $cityName,
                    $radiuses[$cityName],
                    $m,
                    $cnt
                );
            }
        }

        $this->sortResult($result);
        return $result;
    }

    /**
     * @param array $results
     * @return void
     */
    private function sortResult(array &$results): void
    {
        usort($results,
            fn($a, $b) => [$a->getCity(), $a->getMonster()] <=> [$b->getCity(), $b->getMonster()]
        );
    }
}
