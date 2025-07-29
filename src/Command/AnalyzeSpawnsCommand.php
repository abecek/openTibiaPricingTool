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

class AnalyzeSpawnsCommand extends Command
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
            ->addOption('radius', null, InputOption::VALUE_REQUIRED, 'Radius in tiles', 100);
    }

    /**
     * @param InputInterface $in
     * @param OutputInterface $out
     * @return int
     */
    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $spawnPath = $in->getOption('spawn');
        $radius = (int)$in->getOption('radius');
        if (!file_exists($spawnPath)) {
            $out->writeln("<error>Spawn file not found: $spawnPath</error>");
            return Command::FAILURE;
        }

        $cities = CityRegistry::getCities([
            ['city_name'=>'Sagvana','x'=>1299,'y'=>1553,'z'=>7],
            // ... pozostałe miasta
        ]);
        $parser = new SpawnParser();
        $entries = $parser->parse($spawnPath);

        $analyzer = new MonsterProximityAnalyzer();
        $counts = $analyzer->analyze($entries, $cities, $radius);

        $out->writeln("Monster count near each city (within {$radius} tiles):");
        foreach ($counts as $mc) {
            $out->writeln(sprintf("%s: %s → %d", $mc->getCity(), $mc->getMonster(), $mc->getCount()));
        }

        return Command::SUCCESS;
    }
}
