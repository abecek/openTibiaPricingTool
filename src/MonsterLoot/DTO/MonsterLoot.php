<?php
declare(strict_types=1);

namespace App\MonsterLoot\DTO;

readonly class MonsterLoot
{
    /**
     * @param string $monsterName
     * @param array $items
     */
    public function __construct(
        private string $monsterName,
        private array  $items
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
