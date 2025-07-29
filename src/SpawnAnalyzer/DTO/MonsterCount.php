<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer\DTO;

class MonsterCount
{
    private string $city;
    private string $monster;
    private int $count;

    public function __construct(string $city, string $monster, int $count)
    {
        $this->city = $city;
        $this->monster = $monster;
        $this->count = $count;
    }

    public function getCity(): string { return $this->city; }
    public function getMonster(): string { return $this->monster; }
    public function getCount(): int { return $this->count; }
}
