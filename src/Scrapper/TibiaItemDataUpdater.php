<?php
declare(strict_types=1);

namespace App\Scrapper;

use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use RuntimeException;

readonly class TibiaItemDataUpdater
{
    private const int SLEEP_TIME_MICRO_SECS = 100000;

    /**
     * @param string $inputFile
     * @param string $outputFile
     * @param string $format
     * @param UrlBuilder $urlBuilder
     * @param TibiaWikiDataScrapper $dataScrapper
     * @param Logger $logger
     */
    public function __construct(
        private string $inputFile,
        private string $outputFile,
        private string $format,
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
        //$header = array_keys($items[0]);

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
                "Fetched data: %s",
                json_encode($data)
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

            usleep(self::SLEEP_TIME_MICRO_SECS);
        }
        unset($item);

        $writer = new OutputWriter($this->format); // e.g. 'csv' or 'xlsx'
        $writer->write($items, $this->outputFile);

        $this->logger->info(sprintf("Exported updated data to: %s", $this->outputFile));
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
            throw new RuntimeException("File {$this->inputFile} not found.");
        }

        $rows = [];
        if (($h = fopen($this->inputFile, 'r')) === false) {
            throw new RuntimeException("Could not open CSV for reading.");
        }

        $header = fgetcsv($h, 0, ';');
        if (!$header) {
            fclose($h);
            throw new RuntimeException("CSV header is missing or unreadable.");
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
}
