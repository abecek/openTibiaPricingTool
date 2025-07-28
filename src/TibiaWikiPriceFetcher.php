<?php
declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Monolog\Logger;
use Exception;
use RuntimeException;
use GuzzleHttp\Exception\GuzzleException;

class TibiaWikiPriceFetcher
{
    private const string INFO_PAGE_URL = 'https://tibia.fandom.com/wiki/';
    private Client $client;

    /**
     * @param Logger $logger
     * @param array $failedUrls
     */
    public function __construct(
        private readonly Logger $logger,
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
        $url = self::INFO_PAGE_URL . $this->getSlugName($itemName);
        $this->logger->debug("Reading from url: $url");

        try {
            $html = $this->fetchPage($url);
            $crawler = new Crawler($html);

            $sell = $this->extractPriceRange($crawler, 'Sell To');
            $buy = $this->extractPriceRange($crawler, 'Buy From');

            return ['sell' => $sell, 'buy' => $buy];
        } catch (Exception $e) {
            $this->failedUrls[] = $url;
            $this->logger->warning(
                sprintf(
                    "Failed to fetch prices for '%s', error: %s",
                    $itemName,
                    $e->getMessage()
                )
            );
            return ['sell' => null, 'buy' => null, 'failed' => true];
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
        $prices = [];

        $xpathId = $label === 'Sell To' ? 'npc-trade-sellto' : 'npc-trade-buyfrom';
        $crawler->filterXPath("//*[@id='{$xpathId}']//table//tr")->each(function (Crawler $tr) use (&$prices) {
            $tds = $tr->filter('td');
            if ($tds->count() >= 3) {
                $text = trim($tds->eq(2)->text());
                $text = str_replace(',', '', $text);
                if (is_numeric($text)) {
                    $prices[] = (int)$text;
                }
            }
        });

        if (empty($prices)) {
            return null;
        }

        $min = min($prices);
        $max = max($prices);

        return $min === $max ? (string)$min : "{$min}-{$max}";
    }
}
