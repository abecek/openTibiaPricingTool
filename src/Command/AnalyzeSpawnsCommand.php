<?php
declare(strict_types=1);

namespace App\Command;

use App\SpawnAnalyzer\DTO\MonsterCount;
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
            ->addOption('spawnfile', null, InputOption::VALUE_REQUIRED, 'Path to spawn XML file')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug logging to logs/debug.log')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Output format (e.g. csv)', '');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $spawnPath = $input->getOption('spawnfile');
        if (!file_exists($spawnPath)) {
            $output->writeln("<error>Spawn file not found: $spawnPath</error>");
            return Command::FAILURE;
        }

        $outputFileName = null;
        if ($input->getOption('output') === 'csv') {
            $outputFileName = 'data/output/spawn_analysis_output';
        }

        $cities = CityRegistry::getCities([
            ['city_name' => 'Sagvana', 'x' => 1299, 'y' => 1553, 'z' => 7, 'radius' => 200],
            ['city_name' => 'Estimar', 'x' => 1195, 'y' => 1031, 'z' => 7, 'radius' => 200],
            ['city_name' => 'Agren',   'x' => 1786, 'y' => 1313, 'z' => 7, 'radius' => 200],
            ['city_name' => 'Ohara',   'x' => 849,  'y' => 938,  'z' => 7, 'radius' => 200],
            ['city_name' => 'Sacrus',  'x' => 691,  'y' => 1146, 'z' => 7, 'radius' => 200],
        ]);

        $logger = $this->getLogger($input, 'spawns');
        $parser = new SpawnParser($logger);
        $entries = $parser->parse($spawnPath);
        $analyzer = new MonsterProximityAnalyzer();

        $output->writeln("Loaded " . count($entries) . " spawn entries");
        $results = $analyzer->analyze($entries, $cities, $outputFileName);
        $output->writeln("Monster count near each city:");

        $currentCity = '';
        /** @var MonsterCount $entry */
        foreach ($results as $entry) {
            if ($entry->getCity() !== $currentCity) {
                $currentCity = $entry->getCity();
                $output->writeln("");
            }
            $output->writeln(sprintf(
                "%s, radius: %s - %s → %d",
                $entry->getCity(),
                $entry->getRadius(),
                $entry->getMonster(),
                $entry->getCount())
            );
        }

        return Command::SUCCESS;
    }
}
