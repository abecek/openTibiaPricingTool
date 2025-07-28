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
     * @param UrlBuilder $urlBuilder
     * @param TibiaWikiDataScrapper $dataScrapper
     * @param Logger $logger
     */
    public function __construct(
        private string $inputFile,
        private string $outputFile,
        private UrlBuilder $urlBuilder,
        private TibiaWikiDataScrapper $dataScrapper,
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
            $id = $item['id'] ?? '';
            $name = $item['name'] ?? '';
            $this->logger->debug(sprintf("Processing item with id: %s, name: %s", $id, $name));

            if (!$id) {
                $this->logger->warning('Item with missing id, skipped.');
                continue;
            }

            if (!$name) {
                $this->logger->warning('Item with missing name, skipped.');
                continue;
            }

            $url = $this->urlBuilder->getUrl($name);
            $data = $this->dataScrapper->fetchData($id, $name, $url);
            $this->logger->debug(sprintf(
                "Fetched prices, sell: %s, buy: %s",
                $prices['sell'] ?? 'null',
                $prices['buy'] ?? 'null'
            ));

            if (isset($data['failed'])) {
                $item['Is Missing Tibia source'] = 1;
            } else {
                $item['Image'] = $data['image'];
                $item['Url'] = $url;
                $item['Level'] = $data['level'];
                $item['Tibia Sell Price'] = $data['sell'];
                $item['Tibia Buy Price'] = $data['buy'];
            }

            if ($onItemProcessed !== null) {
                $onItemProcessed();
            }

            usleep(150000);
        }
        unset($item);

        $this->writeCsv($items, $header);
        $this->logger->info(sprintf("Exported updated prices to: %s", $this->outputFile));
    }

    /**
     * Reads a CSV file and returns an array of associative arrays.
     * Warns if malformed or inconsistent rows are found.
     *
     * @return array
     */
    private function readCsv(): array
    {
        if (!file_exists($this->inputFile)) {
            throw new \RuntimeException("File {$this->inputFile} not found.");
        }

        $rows = [];
        if (($h = fopen($this->inputFile, 'r')) === false) {
            throw new \RuntimeException("Could not open CSV for reading.");
        }

        $header = fgetcsv($h, 0, ';');
        if (!$header) {
            fclose($h);
            throw new \RuntimeException("CSV header is missing or unreadable.");
        }

        // Remove BOM if present
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $lineNumber = 2; // Bo nagłówek to linia 1

        while (($data = fgetcsv($h, 0, ';')) !== false) {
            // Skip completely empty lines
            if (count($data) === 0 || empty(array_filter($data))) {
                $lineNumber++;
                continue;
            }

            if (count($data) !== count($header)) {
                trigger_error("Line $lineNumber: column count mismatch (got " . count($data) . ", expected " . count($header) . ").", E_USER_WARNING);
                $lineNumber++;
                continue;
            }

            $row = array_combine($header, $data);
            if ($row === false) {
                trigger_error("Line $lineNumber: failed to combine header and row data.", E_USER_WARNING);
                $lineNumber++;
                continue;
            }

            $rows[] = $row;
            $lineNumber++;
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
