<?php
declare(strict_types=1);

namespace App\MonsterLoot;

use App\MonsterLoot\DTO\MonsterLoot;

class MonsterDataProvider
{
    /**
     * @param array<string, MonsterLoot> $loots
     */
    public function __construct(private array $loots)
    {
    }

    /**
     * @param string $monsterName
     * @return MonsterLoot|null
     */
    public function getLoot(string $monsterName): ?MonsterLoot
    {
        return $this->loots[$monsterName] ?? null;
    }
}
