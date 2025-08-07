<?php
declare(strict_types=1);

namespace App\Command;

use App\MonsterLoot\MonsterLootCsvReader;
use App\Pricing\EquipmentCsvReader;
use App\Pricing\PriceSuggestionEngine;
use App\SpawnAnalyzer\SpawnCsvReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

class SuggestPricesCommand extends AbstractCommand
{
    protected static $defaultName = 'suggest:prices';

    protected function configure(): void
    {
        $this
            ->addOption(
                'equipment-csv',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to previously prepared by UpdateDataCommand csv file',
                'data/output/workCopyEquipment_extended.csv'
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $equipmentCsv = $input->getOption('equipment-csv');
        $lootCsv = $input->getOption('loot-csv');
        $spawnCsv = $input->getOption('spawn-csv');

        $lootReader = new MonsterLootCsvReader();
        $spawnReader = new SpawnCsvReader();
        $equipmentReader = new EquipmentCsvReader();

        $lootData = $lootReader->read($lootCsv);
        $spawnData = $spawnReader->read($spawnCsv);
        $equipmentData = $equipmentReader->read($equipmentCsv);

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

        $handle = fopen($equipmentCsv, 'w');
        if (!$handle) {
            throw new RuntimeException("Unable to write to CSV file: " . $equipmentCsv);
        }
        
        if (!empty($updatedRows)) {
            fputcsv($handle, array_keys($updatedRows[0]), ';');
            foreach ($updatedRows as $row) {
                fputcsv($handle, $row, ';');
            }
        }

        fclose($handle);

        $output->writeln('<info>CSV updated with suggested prices.</info>');

        return Command::SUCCESS;
    }
}
