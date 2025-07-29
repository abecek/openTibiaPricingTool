<?php
declare(strict_types=1);

namespace App\SpawnAnalyzer;

use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use App\SpawnAnalyzer\DTO\SpawnEntry;
use \Exception;

class SpawnParser
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

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
            $centerX = (int)$spawn['centerx'];
            $centerY = (int)$spawn['centery'];
            $centerZ = (int)$spawn['centerz'];

            foreach ($spawn->monster as $monster) {
                $offsetX = (int)$monster['x'];
                $offsetY = (int)$monster['y'];
                $offsetZ = (int)$monster['z'];

                $absoluteX = $centerX + $offsetX;
                $absoluteY = $centerY + $offsetY;
                $absoluteZ = $centerZ + $offsetZ;

                $entries[] = new SpawnEntry(
                    (string)$monster['name'],
                    $absoluteX,
                    $absoluteY,
                    $absoluteZ
                );
            }
        }

        return $entries;
    }
}
