<?php
declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Monolog\Logger;
use Exception;
use RuntimeException;
use GuzzleHttp\Exception\GuzzleException;

readonly class TibiaWikiPriceFetcher
{
    private const string INFO_PAGE_URL = 'https://tibia.fandom.com/wiki/';
    private Client $client;

    /**
     * @param Logger $logger
     * @param array $failedUrls
     */
    public function __construct(
        private Logger $logger,
        private array $failedUrls = []
    ) {
        $this->client = new Client(['timeout' => 15]);
    }

    /**
     * @param string $itemName
     * @return array|null[]
     * @throws GuzzleException
     */
    public function fetchPrices(string $itemName): array
    {
        $url = self::INFO_PAGE_URL . "/" . $this->getSlugName($itemName);

        try {
            $html = $this->fetchPage($url);
            $crawler = new Crawler($html);

            $sell = $this->extractPriceRange($crawler, 'Sell To');
            $buy = $this->extractPriceRange($crawler, 'Buy From');

            return ['sell' => $sell, 'buy' => $buy];
        } catch (Exception $e) {
            $this->logger->warning(
                sprintf(
                    "Failed to fetch prices for '%s', error: %s",
                    $itemName,
                    $e->getMessage()
                )
            );
            return ['sell' => null, 'buy' => null];
        }
    }

    /**
     * @return array
     */
    public function getFailedUrls(): array
    {
        return $this->failedUrls;
    }

    /**
     * @param string $itemName
     * @return string
     */
    private function getSlugName(string $itemName): string
    {
        $parts = explode(' ', $itemName);
        $capitalizedParts = array_map('ucfirst', $parts);

        return urlencode(implode('_', $capitalizedParts));
    }

    /**
     * @param string $url
     * @return string
     * @throws GuzzleException
     */
    private function fetchPage(string $url): string
    {
        $res = $this->client->get($url);
        if ($res->getStatusCode() !== 200) {
            throw new RuntimeException("HTTP {$res->getStatusCode()}");
        }
        return (string)$res->getBody();
    }

    /**
     * @param Crawler $crawler
     * @param string $label
     * @return string|null
     */
    private function extractPriceRange(Crawler $crawler, string $label): ?string
    {
        $xpath = "//span[text()='{$label}']/ancestor::h2/following-sibling::div[1]//tr";
        $prices = $crawler->filterXPath($xpath)->each(function (Crawler $tr) {
            $cols = $tr->filter('td')->each(fn(Crawler $td) => trim($td->text()));
            return isset($cols[2]) && is_numeric(str_replace(',', '', $cols[2]))
                ? (int)str_replace(',', '', $cols[2]) : null;
        });

        $prices = array_filter($prices);
        if (empty($prices)) return null;

        $min = min($prices);
        $max = max($prices);

        return $min === $max ? (string)$min : "{$min}-{$max}";
    }
}
