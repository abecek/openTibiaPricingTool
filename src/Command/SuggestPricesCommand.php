<?php
declare(strict_types=1);

namespace App\Command;

use App\MonsterLoot\MonsterLootCsvReader;
use App\Pricing\PriceSuggestionEngine;
use App\Pricing\TibiaPriceProvider;
use App\SpawnAnalyzer\SpawnCsvReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SuggestPricesCommand extends AbstractCommand
{
    protected static $defaultName = 'suggest:prices';

    protected function configure(): void
    {
        $this
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
        $logger = $this->getLogger($input, 'price_suggestion');

        $lootCsv = $input->getOption('loot-csv');
        $spawnCsv = $input->getOption('spawn-csv');

        $spawnReader = new SpawnCsvReader();
        $lootReader = new MonsterLootCsvReader();

        $priceProvider = new TibiaPriceProvider('data/output/workCopyEquipment_extended.csv'); // todo change to option
        $spawnData = $spawnReader->read($spawnCsv);
        $lootData = $lootReader->read($lootCsv);

        $engine = new PriceSuggestionEngine($priceProvider);
        $suggestions = $engine->suggestPrices($spawnData, $lootData);

        foreach ($suggestions as $item => $data) {
            $output->writeln("<info>$item</info>");
            foreach ($data as $city => $priceInfo) {
                $output->writeln(sprintf("  %s: Buy %d / Sell %d", $city, $priceInfo['buy'], $priceInfo['sell']));
            }
        }

        return Command::SUCCESS;
    }
}
