<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\DTO;

class SpawnEntry
{
    private string $monster;
    private int $x;
    private int $y;
    private int $z;

    public function __construct(string $monster, int $x, int $y, int $z)
    {
        $this->monster = $monster;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    public function getMonster(): string { return $this->monster; }
    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getZ(): int { return $this->z; }
}
