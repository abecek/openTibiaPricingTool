<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer;

use SimpleXMLElement;
use App\DTO\SpawnEntry;
use \Exception;

class SpawnParser
{
    /**
     * @param string $path
     * @return array
     * @throws Exception
     */
    public function parse(string $path): array
    {
        $xml = new SimpleXMLElement(file_get_contents($path));
        $entries = [];
        foreach ($xml->spawn as $spawn) {
            $entries[] = new SpawnEntry(
                (string)$spawn->monster,
                (int)$spawn->x,
                (int)$spawn->y,
                (int)$spawn->z
            );
        }
        return $entries;
    }
}
