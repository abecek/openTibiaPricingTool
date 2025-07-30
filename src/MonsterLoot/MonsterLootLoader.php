<?php

namespace App\MonsterLoot;

use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\DTO\MonsterLoot;
use SimpleXMLElement;
use RuntimeException;

class MonsterLootLoader
{
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

            $monsterFile = $basePath . '/' . $map[$name];
            if (!file_exists($monsterFile)) {
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
            $nameOrId = (string)($item['name'] ?? $item['id'] ?? 'unknown');
            $chance = (int)($item['chance'] ?? 0);
            $countMax = isset($item['countmax']) ? (int)$item['countmax'] : null;

            $inside = [];
            if ($item->inside) {
                $inside = $this->parseLoot($item->inside);
            }

            $items[] = new LootItem($nameOrId, $chance, $countMax, $inside);
        }

        return $items;
    }
}
