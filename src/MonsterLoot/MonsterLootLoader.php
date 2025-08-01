<?php
declare(strict_types=1);

namespace App\MonsterLoot;

use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\DTO\MonsterLoot;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use RuntimeException;

readonly class MonsterLootLoader
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param string $basePath Path to the "monster" directory (e.g. /data/monsters/ or absolute full path)
     * @param string[] $monsterNames List of monster names to load
     * @return MonsterDataProvider
     */
    public function loadFromDirectory(string $basePath, array $monsterNames): MonsterDataProvider
    {
        $monsterXml = simplexml_load_file($basePath . '/monsters.xml');
        if (!$monsterXml) {
            throw new RuntimeException("Could not load monsters.xml");
        }

        $map = [];
        foreach ($monsterXml->monster as $entry) {
            $name = (string)$entry['name'];
            $file = (string)$entry['file'];
            $map[$name] = $file;
        }

        $result = [];

        foreach ($monsterNames as $name) {
            if (!isset($map[$name])) {
                continue;
            }

            $monsterFile = $this->normalizePathJoin($basePath, $map[$name]);
            if (!file_exists($monsterFile)) {
                $this->logger->debug(
                    sprintf(
                        'Monster file: "%s" not found (relative path)',
                        $monsterFile
                    )
                );
                continue;
            }

            $xml = simplexml_load_file($monsterFile);
            if (!$xml || !isset($xml->loot)) {
                continue;
            }

            $items = $this->parseLoot($xml->loot);
            $result[$name] = new MonsterLoot($name, $items);
        }

        return new MonsterDataProvider($result);
    }

    /**
     * @param SimpleXMLElement $lootNode
     * @return LootItem[]
     */
    private function parseLoot(SimpleXMLElement $lootNode): array
    {
        $items = [];

        foreach ($lootNode->item as $item) {
            $name = isset($item['name']) ? (string)$item['name'] : null;
            $id = isset($item['id']) ? (int)$item['id'] : null;
            $chance = isset($item['chance']) ? (int)$item['chance'] : 0;
            $countMax = isset($item['countmax']) ? (int)$item['countmax'] : null;

            $inside = [];
            if ($item->inside) {
                $inside = $this->parseLoot($item->inside);
            }

            $items[] = new LootItem(
                $name,
                $id,
                $chance,
                $countMax,
                $inside
            );
        }

        return $items;
    }

    /**
     * Joins two path segments and normalizes all separators to DIRECTORY_SEPARATOR.
     */
    private function normalizePathJoin(string $base, string $relative): string
    {
        $base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base);
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
            ltrim($relative, DIRECTORY_SEPARATOR);
    }

}
