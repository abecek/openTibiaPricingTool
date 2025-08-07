<?php
declare(strict_types=1);

namespace App\MonsterLoot\DTO;

class MonsterLoot
{
    /**
     * @param string $monsterName
     * @param array $items
     */
    public function __construct(
        private readonly string $monsterName,
        private array $items = []
    ) {
    }

    /**
     * @return string
     */
    public function getMonsterName(): string
    {
        return $this->monsterName;
    }

    /**
     * @return LootItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param LootItem $item
     * @return void
     */
    public function addItem(LootItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return LootItem[]
     */
    public function getAllItemsRecursive(): array
    {
        $all = [];
        $stack = $this->items;

        while (!empty($stack)) {
            /** @var LootItem $item */
            $item = array_pop($stack);
            $all[] = $item;

            foreach ($item->getInside() as $nested) {
                $stack[] = $nested;
            }
        }

        return $all;
    }
}
