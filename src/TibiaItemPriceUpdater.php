<?php
declare(strict_types=1);

namespace App;

use Monolog\Logger;
use RuntimeException;
use GuzzleHttp\Exception\GuzzleException;

readonly class TibiaItemPriceUpdater
{
    /**
     * @param string $inputFile
     * @param string $outputFile
     * @param TibiaWikiPriceFetcher $priceFetcher
     * @param Logger $logger
     */
    public function __construct(
        private string $inputFile,
        private string $outputFile,
        private TibiaWikiPriceFetcher $priceFetcher,
        private Logger $logger
    ) {
    }

    /**
     * @param callable|null $onItemProcessed
     * @return void
     * @throws GuzzleException
     */
    public function run(?callable $onItemProcessed = null): void
    {
        $items = $this->readCsv();
        $header = array_keys($items[0]);

        foreach ($items as &$item) {
            $name = $item['name'] ?? '';
            $this->logger->debug("Processing item: $name");

            if (!$name) {
                $this->logger->warning('Item with missing name skipped.');
                continue;
            }

            $prices = $this->priceFetcher->fetchPrices($name);
            $this->logger->debug(sprintf(
                "Fetched prices, sell: %s, buy: %s",
                $prices['sell'] ?? 'null',
                $prices['buy'] ?? 'null'
            ));
            $item['Tibia Sell Price'] = $prices['sell'];
            $item['Tibia Buy Price'] = $prices['buy'];
            if (isset($prices['failed'])) {
                $item['Is Missing Tibia source'] = 1;
            }

            if ($onItemProcessed !== null) {
                $onItemProcessed();
            }

            usleep(100000);
        }
        unset($item);

        $this->writeCsv($items, $header);
        $this->logger->info(sprintf("Exported updated prices to: %s", $this->outputFile));
    }

    /**
     * @return array
     */
    private function readCsv(): array
    {
        if (!file_exists($this->inputFile)) {
            throw new RuntimeException("File {$this->inputFile} not found.");
        }

        $rows = [];
        if (($h = fopen($this->inputFile, 'r')) === false) {
            throw new RuntimeException("Could not open CSV for reading.");
        }

        $header = fgetcsv($h, 0, ';');
        while (($data = fgetcsv($h, 0, ';')) !== false) {
            $rows[] = array_combine($header, $data);
        }

        fclose($h);
        return $rows;
    }

    /**
     * @param array $rows
     * @param array $header
     * @return void
     */
    private function writeCsv(array $rows, array $header): void
    {
        $h = fopen($this->outputFile, 'w');
        fputcsv($h, $header, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($h, $line, ';');
        }

        fclose($h);
    }
}
