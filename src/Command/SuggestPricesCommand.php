<?php
declare(strict_types=1);

namespace App\Command;

use App\MonsterLoot\MonsterLootCsvReader;
use App\Pricing\EquipmentCsvReader;
use App\Pricing\EquipmentXlsxReader;
use App\Pricing\EquipmentXlsxWriter;
use App\Pricing\PriceSuggestionEngine;
use App\SpawnAnalyzer\SpawnCsvReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;
use InvalidArgumentException;

class SuggestPricesCommand extends AbstractCommand
{
    protected static $defaultName = 'suggest:prices';

    protected function configure(): void
    {
        $this
            ->addOption(
                'equipment-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to previously prepared by UpdateDataCommand file',
                'data/output/workCopyEquipment_extended'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'File format for equipment file: csv or xlsx',
                'csv'
            )
            ->addOption(
                'loot-csv',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to monster_loot_output.csv',
                'data/output/monster_loot_output.csv'
            )
            ->addOption(
                'spawn-csv',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to spawn_analysis_output.csv',
                'data/output/spawn_analysis_output.csv'
            )
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_NONE,
                'Enable debug output'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $equipmentPath = $input->getOption('equipment-file');
        $equipmentFormat = strtolower((string)$input->getOption('format'));
        $equipmentPath .= '.' . $equipmentFormat;
        $lootCsv = $input->getOption('loot-csv');
        $spawnCsv = $input->getOption('spawn-csv');

        if (!in_array($equipmentFormat, ['csv', 'xlsx'])) {
            throw new InvalidArgumentException("Invalid format: $equipmentFormat. Use csv or xlsx.");
        }

        $lootReader = new MonsterLootCsvReader();
        $spawnReader = new SpawnCsvReader();
        $equipmentReader = $equipmentFormat === 'xlsx'
            ? new EquipmentXlsxReader()
            : new EquipmentCsvReader();

        $lootData = $lootReader->read($lootCsv);
        $spawnData = $spawnReader->read($spawnCsv);
        $equipmentData = $equipmentReader->read($equipmentPath);

        $engine = new PriceSuggestionEngine();
        $suggestions = $engine->suggestPrices($spawnData, $equipmentData, $lootData);

        $updatedRows = [];
        foreach ($equipmentData as $row) {
            $itemName = strtolower(trim($row['name'] ?? ''));
            if ($itemName === '' || !isset($suggestions[$itemName])) {
                $updatedRows[] = $row;
                continue;
            }

            $buyPerCity = [];
            $sellPerCity = [];
            foreach ($suggestions[$itemName] as $city => $prices) {
                $buyPerCity[$city] = $prices['buy'];
                $sellPerCity[$city] = $prices['sell'];
            }

            $row['Buy'] = json_encode($buyPerCity, JSON_UNESCAPED_UNICODE);
            $row['Sell'] = json_encode($sellPerCity, JSON_UNESCAPED_UNICODE);

            $updatedRows[] = $row;
        }

        if ($equipmentFormat === 'xlsx') {
            $writer = new EquipmentXlsxWriter();
            $writer->write($equipmentPath, $updatedRows);
        } else {
            $handle = fopen($equipmentPath, 'w');
            if (!$handle) {
                throw new RuntimeException("Unable to write to CSV file: $equipmentPath");
            }

            if (!empty($updatedRows)) {
                fputcsv($handle, array_keys($updatedRows[0]), ';');
                foreach ($updatedRows as $row) {
                    fputcsv($handle, $row, ';');
                }
            }

            fclose($handle);
        }

        $output->writeln('<info>Equipment file updated with suggested prices.</info>');
        
        return Command::SUCCESS;
    }
}
