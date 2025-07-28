<?php
declare(strict_types=1);

namespace App\Command;

use App\TibiaItemDataUpdater;
use App\TibiaWikiDataScrapper;
use App\UrlBuilder;
use Monolog\Formatter\FormatterInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

class UpdatePricesCommand extends Command
{
    protected static $defaultName = 'update-data';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Fetches NPC buy/sell prices & additional data from TibiaWiki and updates a CSV file.')
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Path to input CSV file', 'workCopyEquipment.csv')
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

        $logger = $this->getLogger($input);

        $urlBuilder = new UrlBuilder($logger);
        $fetcher = new TibiaWikiDataScrapper($logger);
        $updater = new TibiaItemDataUpdater(
            $inputFile,
            $outputFile . '.' . $format,
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
            $output->writeln("<info>Data updated successfully. Output saved to: $outputFile</info>");

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

    /**
     * @param InputInterface $input
     * @return Logger
     */
    private function getLogger(InputInterface $input): Logger
    {
        $logger = new Logger('tibia_data');
        $logger->pushHandler(new StreamHandler('logs/error.log', Level::Warning));
        if ($input->getOption('debug')) {
            // Log to debug file
            $logger->pushHandler(new StreamHandler('logs/debug.log', Level::Debug));

            // Log to STDOUT with color support
            $consoleStream = fopen('php://stdout', 'w');
            $consoleHandler = new StreamHandler($consoleStream, Level::Debug);

            // Add colorized formatter
            $consoleHandler->setFormatter(new class implements FormatterInterface {
                public function format(LogRecord $record): string
                {
                    $level = $record->level->getName();
                    $time = $record->datetime->format('H:i:s');

                    $levelColor = match ($level) {
                        'DEBUG'     => "\033[0;37m",      // jasnoszary
                        'INFO'      => "\033[0;34m",      // niebieski
                        'WARNING'   => "\033[1;33m",      // żółty
                        'ERROR'     => "\033[0;31m",      // czerwony
                        'CRITICAL'  => "\033[1;37;41m",   // biały na czerwonym tle
                        default     => "\033[0m",
                    };

                    return sprintf(
                        "\033[0;90m[%s]\033[0m %s%s\033[0m: %s\n",
                        $time,
                        $levelColor,
                        $level,
                        $record->message
                    );
                }

                public function formatBatch(array $records): string
                {
                    return implode('', array_map([$this, 'format'], $records));
                }
            });

            $logger->pushHandler($consoleHandler);
        }

        return $logger;
    }
}
