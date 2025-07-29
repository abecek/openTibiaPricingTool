<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer;

use App\SpawnAnalyzer\DTO\City;

class CityRegistry
{
    /**
     * @param array $definitions
     * @return array
     */
    public static function getCities(array $definitions): array
    {
        $cities = [];
        foreach ($definitions as $d) {
            $cities[] = new City(
                $d['city_name'], (int)$d['x'], (int)$d['y'], (int)$d['z']
            );
        }
        return $cities;
    }
}
