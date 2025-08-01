<?php
declare(strict_types=1);

namespace App\Command;

use App\Item\ItemLookupService;
use App\MonsterLoot\DTO\LootItem;
use App\MonsterLoot\MonsterLootLoader;
use App\MonsterLoot\SpawnLootIntegrator;
use App\SpawnAnalyzer\SpawnCsvReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LoadMonsterLootCommand extends AbstractCommand
{
    protected static $defaultName = 'monster:load-loot';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'monster-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to monster directory (must include monsters.xml)'
            )
            ->addOption(
                'spawn-csv',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to spawn_analysis_output.csv file',
                'data/output/spawn_analysis_output.csv'
            )
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_NONE,
                'Enable debug mode'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->getLogger($input, 'monster_loot');

        $monsterDir = $input->getOption('monster-dir');
        $csvPath = $input->getOption('spawn-csv');

        if (!$monsterDir || !is_dir($monsterDir)) {
            $logger->error("Invalid or missing monster directory.");
            return Command::FAILURE;
        }

        if (!$csvPath || !is_file($csvPath)) {
            $logger->error("Invalid or missing CSV file with spawn analysis.");
            return Command::FAILURE;
        }

        // 1. Read spawn data (MonsterCount[])
        $reader = new SpawnCsvReader();
        $spawnData = $reader->read($csvPath);
        $logger->info("Loaded spawn data for " . count($spawnData) . " entries.");

        // 2. Extract monster names
        $monsterNames = array_unique(array_map(fn($s) => $s->getMonster(), $spawnData));
        $logger->info("Detected " . count($monsterNames) . " unique monsters.");

        // 3. Load loot data
        $loader = new MonsterLootLoader($logger);
        $provider = $loader->loadFromDirectory($monsterDir, $monsterNames);

        // 4. Integrate spawn+loot
        $integrator = new SpawnLootIntegrator();
        $result = $integrator->integrate($provider, $spawnData);

        // 5. Print results to screen
        $itemLookup = new ItemLookupService('data/input/items.xml');
        foreach ($result as $city => $monsters) {
            $output->writeln("<info>City: $city</info>");
            foreach ($monsters as $monster => $loot) {
                $output->writeln("  <comment>$monster:</comment>");
                foreach ($loot->getItems() as $item) {
                    $this->printLootItem($output, $item, $itemLookup);
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param OutputInterface $output
     * @param LootItem $item
     * @param ItemLookupService $itemLookup
     * @param int $indentLevel
     * @return void
     */
    private function printLootItem(
        OutputInterface $output,
        LootItem $item,
        ItemLookupService $itemLookup,
        int $indentLevel = 2
    ): void {
        $name = $item->getName();
        $id = $item->getId();

        if (!$name && $id !== null) {
            $name = $itemLookup->getNameById($id);
        }
        if (!$id && $name !== null) {
            $id = $itemLookup->getIdByName($name);
        }

        $label = $name ? "{$name} (ID: {$id})" : "ID: {$id}";
        $indent = str_repeat(' ', $indentLevel * 2);

        $output->writeln(sprintf(
            "%s- %s (chance: %d, countMax: %s)",
            $indent,
            $label,
            $item->getChance(),
            $item->getCountMax() ?? 'n/a'
        ));

        foreach ($item->getInside() as $nested) {
            $this->printLootItem($output, $nested, $itemLookup, $indentLevel + 1);
        }
    }
}
