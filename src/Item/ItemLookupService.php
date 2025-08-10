<?php
declare(strict_types=1);

namespace App\Item;

use RuntimeException;

class ItemLookupService
{
    private array $idToName = [];
    private array $nameToId = [];

    /**
     * @param string $itemsXmlPath
     */
    public function __construct(string $itemsXmlPath)
    {
        if (!file_exists($itemsXmlPath)) {
            throw new RuntimeException("items.xml file not found: $itemsXmlPath");
        }

        $xml = simplexml_load_file($itemsXmlPath);
        if (!$xml) {
            throw new RuntimeException("Failed to parse items.xml");
        }

        foreach ($xml->item as $item) {
            $id = (int) $item['id'];
            $name = strtolower((string) $item['name']);

            $this->idToName[$id] = $name;
            $this->nameToId[$name] = $id;
        }
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function getNameById(int $id): ?string
    {
        return $this->idToName[$id] ?? null;
    }

    /**
     * @param string $name
     * @return int|null
     */
    public function getIdByName(string $name): ?int
    {
        return $this->nameToId[strtolower($name)] ?? null;
    }
}
