<?php
declare(strict_types=1);

namespace App\Command;

use App\TibiaItemPriceUpdater;
use App\TibiaWikiPriceFetcher;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

class UpdatePricesCommand extends Command
{
    protected static $defaultName = 'tibia:update-prices';

    protected function configure(): void
    {
        $this
            ->setDescription('Fetches NPC buy/sell prices from TibiaWiki and updates a CSV file.')
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Path to input CSV file', 'workCopyEquipment.csv')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Path to output CSV file', 'workCopyEquipment_with_prices.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFile = $input->getOption('input');
        $outputFile = $input->getOption('output');

        $logger = new Logger('tibia_prices');
        $logger->pushHandler(new StreamHandler('logs/error.log', Logger::WARNING));

        $fetcher = new TibiaWikiPriceFetcher($logger);
        $updater = new TibiaItemPriceUpdater($inputFile, $outputFile, $fetcher, $logger);

        try {
            if (!file_exists($inputFile)) {
                $output->writeln("<error>Input file not found: $inputFile</error>");
                return Command::FAILURE;
            }

            $lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $total = max(count($lines) - 1, 0); // subtract header

            $progressBar = new ProgressBar($output, $total);
            $progressBar->start();

            $updater->run(function () use (&$progressBar) {
                $progressBar->advance();
            });

            $progressBar->finish();
            $output->writeln("");
            $output->writeln("<info>Prices updated successfully. Output saved to: $outputFile</info>");

            $failedUrls = $fetcher->getFailedUrls();
            foreach ($failedUrls as $url) {
                $output->writeln("<info>Failed to fetch url: $url</info>");
            }
            $output->writeln("<info>Failed urls count: " . count($failedUrls) . "</info>");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $logger->error('Unexpected critical error: ' . $e->getMessage());
            $output->writeln('<error>Failed to update prices. Check logs/error.log</error>');

            return Command::FAILURE;
        }
    }
}
