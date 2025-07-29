<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\DTO;

class City
{
    private string $name;
    private int $x;
    private int $y;
    private int $z;

    public function __construct(string $name, int $x, int $y, int $z)
    {
        $this->name = $name;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    public function getName(): string { return $this->name; }
    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getZ(): int { return $this->z; }
}
