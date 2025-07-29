<?php
declare(strict_types=1);

namespace App\Command;

use App\SpawnAnalyzer\SpawnParser;
use App\SpawnAnalyzer\CityRegistry;
use App\SpawnAnalyzer\MonsterProximityAnalyzer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

class AnalyzeSpawnsCommand extends AbstractCommand
{
    protected static $defaultName = 'analyze:spawns';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Analyze monster spawns by proximity to cities')
            ->addOption('spawn', null, InputOption::VALUE_REQUIRED, 'Path to spawn XML file')
            ->addOption('radius', null, InputOption::VALUE_REQUIRED, 'Radius in tiles', 100)
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug logging to logs/debug.log');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $spawnPath = $input->getOption('spawn');
        $radius = (int)$input->getOption('radius');
        if (!file_exists($spawnPath)) {
            $output->writeln("<error>Spawn file not found: $spawnPath</error>");
            return Command::FAILURE;
        }

        $cities = CityRegistry::getCities([
            ['city_name'=>'Sagvana','x'=>1299,'y'=>1553,'z'=>7],
            // ... pozostałe miasta
        ]);

        $logger = $this->getLogger($input, 'spawns');
        $parser = new SpawnParser($logger);
        $entries = $parser->parse($spawnPath);

        $analyzer = new MonsterProximityAnalyzer();

        $output->writeln("Loaded " . count($entries) . " spawn entries");
        $counts = $analyzer->analyze($entries, $cities, $radius);

        $output->writeln("Monster count near each city (within {$radius} tiles):");
        foreach ($counts as $mc) {
            $output->writeln(sprintf("%s: %s → %d", $mc->getCity(), $mc->getMonster(), $mc->getCount()));
        }

        return Command::SUCCESS;
    }
}
