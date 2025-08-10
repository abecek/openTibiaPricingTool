<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\DTO;

class SpawnEntry
{
    /**
     * @param string $monster
     * @param int $x
     * @param int $y
     * @param int $z
     */
    public function __construct(
        private string $monster,
        private int $x,
        private int $y,
        private int $z
    ) {
    }

    public function getMonster(): string { return $this->monster; }
    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getZ(): int { return $this->z; }
}
