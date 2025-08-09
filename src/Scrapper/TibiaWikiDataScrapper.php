<?php
declare(strict_types=1);

namespace App\Scrapper;

use Throwable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class TibiaWikiDataScrapper
{
    private const string IMAGES_PATH = 'images';

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
     * @param string $id
     * @param string $itemName
     * @param string $url
     * @return array
     * @throws GuzzleException
     */
    public function fetchData(string $id, string $itemName, string $url): array
    {
        try {
            $html = $this->fetchPage($url);
            $crawler = new Crawler($html);

            // old logic, returns single value or range: 10-250
//            $sell = $this->extractPriceRange($crawler, 'Sell To');
//            $buy = $this->extractPriceRange($crawler, 'Buy From');
            $sell = json_encode($this->extractPricePerCity($crawler, 'Sell To'), JSON_UNESCAPED_UNICODE);
            $buy  = json_encode($this->extractPricePerCity($crawler, 'Buy From'), JSON_UNESCAPED_UNICODE);

            return [
                'image' => $this->extractItemImage($crawler, $id, $itemName),
                'level' => $this->extractRequiredLevel($crawler),
                'sell' => $sell,
                'buy' => $buy
            ];
        } catch (Exception $e) {
            $this->failedUrls[] = $url;
            $this->logger->warning(
                sprintf(
                    "Failed to fetch data for '%s', error: %s",
                    $itemName,
                    $e->getMessage()
                )
            );
            return [
                'image' => null,
                'level' => null,
                'sell' => null,
                'buy' => null,
                'failed' => true
            ];
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

        try {
            $rows = $crawler->filterXPath("//*[@id='{$xpathId}']//tr");
        } catch (\Throwable $e) {
            return null;
        }

        $rows->each(function (Crawler $tr) use (&$prices) {
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

    /**
     * Extracts per-city prices as full lists for a given label ("Sell To" or "Buy From").
     * Example output: ['Carlin' => [240, 240], 'Thais' => [240, 25], ...]
     *
     * @param Crawler $crawler
     * @param 'Sell To'|'Buy From' $label
     * @return array<string,int[]>
     */
    private function extractPricePerCity(Crawler $crawler, string $label): array
    {
        $xpathId = $label === 'Sell To' ? 'npc-trade-sellto' : 'npc-trade-buyfrom';

        try {
            $rows = $crawler->filterXPath("//*[@id='{$xpathId}']//tr");
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf(
                    'Could not get price data for xpath: %s, error: %s',
                    "//*[@id='{$xpathId}']//tr",
                    $e->getMessage()
                )
            );
            return [];
        }

        $perCity = []; // city => int[]
        $rows->each(function (Crawler $tr) use (&$perCity) {
            $tds = $tr->filter('td');
            if ($tds->count() < 3) {
                return;
            }

            $city = trim($tds->eq(1)->text()); // "Location" column
            $priceText = str_replace([',', ' '], '', trim($tds->eq(2)->text()));

            if ($city === '' || stripos($city, 'Houses and Guildhalls') !== false) {
                return;
            }

            if (is_numeric($priceText)) {
                $perCity[$city] ??= [];
                $perCity[$city][] = (int)$priceText;
            }
        });

        return $perCity;
    }

    /**
     * @param Crawler $crawler
     * @return int|null
     */
    private function extractRequiredLevel(Crawler $crawler): ?int
    {
        try {
            // Attempt to locate the sidebar field where required level may be listed
            $node = $crawler->filterXPath('//*[@id="mw-content-text"]/div/div/aside/section[1]/div[1]/div');

            if ($node->count()) {
                $text = trim($node->text());

                // Look for numeric value like "47", "100", etc.
                if (preg_match('/\d+/', $text, $matches)) {
                    return (int)$matches[0];
                }
            }
        } catch (Throwable $e) {
            $this->logger->debug('Level requirement not found or failed to parse.');
        }

        return null;
    }

    /**
     * @param Crawler $crawler
     * @param $id
     * @param string $slugName
     * @return string|null
     */
    private function extractItemImage(Crawler $crawler, $id, string $slugName): ?string
    {
        try {
            $node = $crawler->filterXPath('//*[@id="mw-content-text"]/div/div/aside/figure/a/img');

            if ($node->count()) {
                $url = $node->attr('src');
                $res = $this->downloadImage($url, $id, $slugName);

                $this->logger->debug("Found image URL: $url");

                return $res;
            }
        } catch (Throwable $e) {
            $this->logger->debug("Image not found or failed for slug: $slugName");
        }

        return null;
    }

    /**
     * @param string $url
     * @param string $id
     * @param string $slugName
     * @return string|null
     */
    private function downloadImage(string $url, string $id, string $slugName): ?string
    {
        try {
            if (!is_dir(self::IMAGES_PATH)) {
                mkdir(self::IMAGES_PATH, 0777, true);
            }

            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'gif';
            $fileName = strtolower($slugName) . '.' . $ext;
            $localPath = self::IMAGES_PATH . "/$fileName";

            if (!file_exists($localPath)) {
                $image = file_get_contents($url);
                if ($image !== false) {
                    file_put_contents($localPath, $image);
                }
            }

            return $localPath;
        } catch (Throwable $e) {
            $this->logger->warning("Failed to download image from $url: " . $e->getMessage());
        }

        return null;
    }

}
