<?php
declare(strict_types=1);

namespace App\Command;

use App\Scrapper\TibiaItemDataUpdater;
use App\Scrapper\TibiaWikiDataScrapper;
use App\Scrapper\UrlBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class UpdateDataCommand extends AbstractCommand
{
    protected static $defaultName = 'update-data';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Fetches NPC buy/sell prices & additional data from TibiaWiki and updates a CSV file.')
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Path to input CSV file', 'data/workCopyEquipment.csv')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output file name', 'workCopyEquipment_extended')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output file format (csv or xlsx)', 'csv')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug logging to logs/debug.log');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFile = $input->getOption('input');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');

        $outputFileName = $outputFile . '.' . $format;

        $logger = $this->getLogger($input, 'tibia_data');

        $urlBuilder = new UrlBuilder($logger);
        $fetcher = new TibiaWikiDataScrapper($logger);
        $updater = new TibiaItemDataUpdater(
            $inputFile,
            $outputFileName,
            $format,
            $urlBuilder,
            $fetcher,
            $logger
        );

        try {
            if (!file_exists($inputFile)) {
                $output->writeln("<error>Input file not found: $inputFile</error>");
                return Command::FAILURE;
            }

            $lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $total = max(count($lines) - 1, 0); // subtract header

            $progressBar = new ProgressBar($output, $total);
            $progressBar->start();

            $logger->debug('Process has started...');
            $updater->run(function () use (&$progressBar) {
                $progressBar->advance();
            });

            $progressBar->finish();
            $output->writeln("");
            $output->writeln("<info>Data updated successfully. Output saved to: $outputFileName</info>");

            $failedUrls = $fetcher->getFailedUrls();
            foreach ($failedUrls as $url) {
                $output->writeln("<info>Failed to fetch url: $url</info>");
            }
            $output->writeln("<info>Failed urls count: " . count($failedUrls) . "</info>");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $logger->error('Unexpected critical error: ' . $e->getMessage());
            $output->writeln('<error>Failed to update data. Check logs/error.log</error>');

            return Command::FAILURE;
        }
    }
}
