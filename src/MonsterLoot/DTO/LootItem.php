<?php
declare(strict_types=1);

namespace App\MonsterLoot\DTO;

class LootItem
{
    /**
     * @param string $nameOrId
     * @param int $chance
     * @param int|null $countMax
     * @param array $inside
     */
    public function __construct(
        private readonly string $nameOrId,
        private readonly int $chance,
        private readonly ?int $countMax = null,
        private readonly array $inside = []
    ) {
    }

    /**
     * @return string
     */
    public function getNameOrId(): string { return $this->nameOrId; }

    /**
     * @return int
     */
    public function getChance(): int { return $this->chance; }

    /**
     * @return int|null
     */
    public function getCountMax(): ?int { return $this->countMax; }

    /**
     * @return LootItem[]
     */
    public function getInside(): array { return $this->inside; }
}
