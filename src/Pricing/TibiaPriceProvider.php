<?php
declare(strict_types=1);

namespace App\Pricing;

class TibiaPriceProvider
{
    private array $priceDataById = [];

    /**
     * @param string $csvPath
     */
    public function __construct(string $csvPath)
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException("Price CSV not found: $csvPath");
        }

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle, 0, ';');

        // Map column indices
        $indexMap = array_flip($header);
        $idIdx = $indexMap['id'] ?? null;
        $buyIdx = $indexMap['Tibia Buy Price'] ?? null;
        $sellIdx = $indexMap['Tibia Sell Price'] ?? null;

        if ($idIdx === null || $buyIdx === null || $sellIdx === null) {
            throw new \RuntimeException("Missing expected columns in price CSV");
        }

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $id = (int) $row[$idIdx];
            $buy = is_numeric($row[$buyIdx]) ? (int) $row[$buyIdx] : null;
            $sell = is_numeric($row[$sellIdx]) ? (int) $row[$sellIdx] : null;

            $this->priceDataById[$id] = [
                'buy' => $buy,
                'sell' => $sell,
            ];
        }

        fclose($handle);
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        return $this->priceDataById[$id] ?? null;
    }
}
