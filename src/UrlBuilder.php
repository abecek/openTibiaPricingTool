<?php
declare(strict_types=1);

namespace App;

use Psr\Log\LoggerInterface;

readonly class UrlBuilder
{
    private const string INFO_PAGE_URL = 'https://tibia.fandom.com/wiki/';

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param string $itemName
     * @return string
     */
    public function getUrl(string $itemName): string
    {
        $url = self::INFO_PAGE_URL . $this->getSlugName($itemName);
        $this->logger->debug("Reading from url: $url");

        return $url;
    }

    /**
     * @param string $itemName
     * @return string
     */
    private function getSlugName(string $itemName): string
    {
        $parts = explode(' ', $itemName);
        $callable = function (string $part) {
            if ($part === 'of') {
                return $part;
            }

            return ucfirst($part);
        };
        //$capitalizedParts = array_map('ucfirst', $parts);
        $capitalizedParts = array_map('ucfirst', $parts);

        return urlencode(implode('_', $capitalizedParts));
    }
}