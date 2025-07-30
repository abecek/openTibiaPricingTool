<?php

namespace App\MonsterLoot\DTO;

class MonsterLoot
{
    /**
     * @param string $monsterName
     * @param array $items
     */
    public function __construct(
        private readonly string $monsterName,
        private readonly array $items
    ) {

    }

    /**
     * @return string
     */
    public function getMonsterName(): string { return $this->monsterName; }

    /**
     * @return LootItem[]
     */
    public function getItems(): array { return $this->items; }
}
