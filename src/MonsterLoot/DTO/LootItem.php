<?php
declare(strict_types=1);

namespace App\MonsterLoot\DTO;

readonly class LootItem
{
    /**
     * @param string|null $name
     * @param int|null $id
     * @param int $chance
     * @param int|null $countMax
     * @param array|null $inside
     */
    public function __construct(
        private ?string $name,
        private ?int $id,
        private int $chance,
        private ?int $countMax = null,
        private ?array $inside = []
    ) {
    }

    /**
     * @return string|null
     */
    public function getName(): ?string{ return $this->name; }

    /**
     * @return int|null
     */
    public function getId(): ?int{ return $this->id; }

    /**
     * @return string
     */
    public function getNameOrId(): string { return $this->name ?? (string) $this->id; }

    /**
     * @return int
     */
    public function getChance(): int { return $this->chance; }

    /**
     * @return int|null
     */
    public function getCountMax(): ?int { return $this->countMax; }

    /**
     * @return LootItem[]|null
     */
    public function getInside(): ?array { return $this->inside; }
}
