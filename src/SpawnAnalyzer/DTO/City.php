<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\DTO;

class City
{
    /**
     * @param string $name
     * @param int $radius
     * @param int $x
     * @param int $y
     * @param int $z
     */
    public function __construct(
        private string $name,
        private int $radius,
        private int $x,
        private int $y,
        private int $z
    ) {
    }

    public function getName(): string { return $this->name; }
    public function getRadius(): int { return $this->radius; }
    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getZ(): int { return $this->z; }
}
