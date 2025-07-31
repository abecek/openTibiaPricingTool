<?php
declare(strict_types=1);

namespace App\Command;

use App\MonsterLoot\MonsterLootLoader;
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
            ->addOption('monster-dir', null, InputOption::VALUE_REQUIRED, 'Path to monster directory (must include monsters.xml)')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug mode');
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

        if (!$monsterDir || !is_dir($monsterDir)) {
            $logger->error("Invalid or missing monster directory: " . ($monsterDir ?? 'null'));
            return Command::FAILURE;
        }

        // Example hardcoded list, to be replaced with dynamic spawn-based list
        $monsterNames = [
            'Barbarian Headsplitter',
            'Bear',
            'Panda',
            'Medusa'
        ];

        $loader = new MonsterLootLoader($logger);
        $provider = $loader->loadFromDirectory($monsterDir, $monsterNames);

        foreach ($monsterNames as $name) {
            $loot = $provider->getLoot($name);
            if (!$loot) {
                $logger->warning("No loot found for: {$name}");
                continue;
            }

            $logger->info("Loot for {$name}:");
            foreach ($loot->getItems() as $item) {
                $logger->info(sprintf(
                    "- %s (chance: %d, countMax: %s)",
                    $item->getNameOrId(),
                    $item->getChance(),
                    $item->getCountMax() ?? 'null'
                ));
            }
        }

        return Command::SUCCESS;
    }
}
