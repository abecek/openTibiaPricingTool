<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\DTO;

class MonsterCount
{
    /**
     * @param string $city
     * @param int $radius
     * @param string $monster
     * @param int $count
     */
    public function __construct(
        private string $city,
        private int $radius,
        private string $monster,
        private int $count
    ) {
    }

    public function getCity(): string { return $this->city; }
    public function getRadius(): int { return $this->radius; }
    public function getMonster(): string { return $this->monster; }
    public function getCount(): int { return $this->count; }
}
