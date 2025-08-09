<?php
declare(strict_types=1);

namespace App\Command;

use App\Pricing\EquipmentCsvReader;
use App\Pricing\EquipmentXlsxReader;
use App\Merchant\LuaTableDumper;
use App\Merchant\MerchantDataValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use stdClass;

class GenerateMerchantItemsCommand extends Command
{
    protected static $defaultName = 'merchant:generate-items';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Generate Lua items/*.lua for merchant system from CSV/XLSX.')
            ->addOption(
                'equipment-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to workCopyEquipment_extended.csv/xlsx',
                'data/output/workCopyEquipment_extended.csv'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Input format: csv|xlsx (auto by extension if omitted)'
            )
            ->addOption(
                'dst-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Destination of for TFS Lua items directory (from Merchan System)',
                'data/lib/core/customs/merchant/items'
            )
            ->addOption(
                'fail-on-warnings',
                null,
                InputOption::VALUE_NONE,
                'Treat warnings as errors (exit != 0).'
            )
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_NONE,
                'Print generation stats and extra info.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getOption('equipment-file');
        $formatOpt = $input->getOption('format');
        $dstDir = (string)$input->getOption('dst-dir');
        $failOnWarnings = (bool)$input->getOption('fail-on-warnings');
        $debug = (bool)$input->getOption('debug');

        $format = $formatOpt ? strtolower((string)$formatOpt) : (str_ends_with(strtolower($path), '.xlsx') ? 'xlsx' : 'csv');
        $reader = $format === 'xlsx' ? new EquipmentXlsxReader() : new EquipmentCsvReader();

        if (!is_dir($dstDir) && !@mkdir($dstDir, 0777, true) && !is_dir($dstDir)) {
            $output->writeln("<error>Cannot create destination dir: {$dstDir}</error>");
            return Command::FAILURE;
        }

        $rows = $reader->read($path);

        if ($debug && !empty($rows)) {
            $output->writeln('<comment>Detected headers:</comment> ' . implode(' | ', array_keys($rows[0])));
            // pokaż surowe wartości newralgicznych kolumn dla 1. rekordu
            $sample = $rows[0];
            $output->writeln('<comment>Sample Buy cell:</comment> ' . substr((string)($sample['Buy'] ?? ''), 0, 120));
            $output->writeln('<comment>Sample Sell cell:</comment> ' . substr((string)($sample['Sell'] ?? ''), 0, 120));
        }

        // Validation (errors only)
        $validator = new MerchantDataValidator();
        $errors = $validator->validate($rows);
        if ($errors) {
            foreach ($errors as $err) {
                $output->writeln("<error>{$err}</error>");
            }
            $output->writeln('<error>Merchant data validation failed. Some data can be incorrect</error>');
            //return Command::FAILURE;
        }

        // Partition & warnings & stats
        $weapons = [];
        $wands = [];
        $equip = [];

        $warnings = [];
        $stats = [
            'total' => 0,
            'weapons' => 0,
            'weapons_by_type' => [
                'sword' => 0, 'axe' => 0, 'club' => 0,
                'distance' => 0, 'bow' => 0, 'crossbow' => 0, 'spear' => 0, 'throwing' => 0,
            ],
            'wands' => 0,
            'equipment' => 0,
            'empty_buy' => 0,
            'empty_sell' => 0,
            'missing_group' => 0,
            'routed_equipment_unknown' => 0,
        ];

        foreach ($rows as $i => $row) {
            $stats['total']++;

            $id = isset($row['id']) ? (int)$row['id'] : null;
            $name = trim((string)($row['name'] ?? ''));
            $weaponType = strtolower(trim((string)($row['weaponType'] ?? '')));
            $slotType = strtolower(trim((string)($row['slotType'] ?? '')));

            $group = $this->deriveGroup($weaponType, $slotType, $row);
            if ($group === null) {
                $warnings[] = "Row " . ($i+2) . ": missing/unknown group (weaponType='{$weaponType}', slotType='{$slotType}'). Routed to equipment.";
                $stats['missing_group']++;
            }

            $buyMap = $this->parseCityPriceMap((string)($row['Buy'] ?? ''));
            $sellMap = $this->parseCityPriceMap((string)($row['Sell'] ?? ''));

            if (empty($buyMap))  { $stats['empty_buy']++;  $warnings[] = "Row " . ($i+2) . ": Buy JSON empty (id={$id}, name='{$name}')."; }
            if (empty($sellMap)) { $stats['empty_sell']++; $warnings[] = "Row " . ($i+2) . ": Sell JSON empty (id={$id}, name='{$name}')."; }

            $luaItem = [
                'id' => $id,
                'name' => $name,
                'slotType' => $slotType ?: null,
                'group' => $group,
                'subType' => 0,
                'buy' => $buyMap ?: new \stdClass(),
                'sell' => $sellMap ?: new \stdClass(),
            ];

            // route to file + stats
            if ($this->isWandOrRod($weaponType, $row)) {
                $wands[$id] = $luaItem;
                $stats['wands']++;
            } elseif ($this->isWeapon($weaponType)) {
                $weapons[$id] = $luaItem;
                $stats['weapons']++;
                if (isset($stats['weapons_by_type'][$weaponType])) {
                    $stats['weapons_by_type'][$weaponType]++;
                }
            } else {
                $equip[$id] = $luaItem;
                $stats['equipment']++;
                if ($group === null) {
                    $stats['routed_equipment_unknown']++;
                }
            }
        }

        // Write Lua files
        $files = [
            'weapons.lua' => $weapons,
            'wands.lua' => $wands,
            'equipment.lua' => $equip,
        ];

        foreach ($files as $fname => $data) {
            $pathOut = rtrim($dstDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
            $lua = "-- Auto-generated. Do not edit by hand.\n"
                . "local ITEMS = " . LuaTableDumper::dump($this->wrapWithKeys($data)) . "\n"
                . "return ITEMS\n";
            file_put_contents($pathOut, $lua);
            $output->writeln("<info>Written {$fname} (" . count($data) . " items)</info>");
        }

        // Debug stats output
        if ($debug) {
            $output->writeln('');
            $output->writeln('<comment>=== Generation stats ===</comment>');
            $output->writeln("Total rows:          {$stats['total']}");
            $output->writeln("Weapons total:       {$stats['weapons']}");
            $output->writeln("  swords:            {$stats['weapons_by_type']['sword']}");
            $output->writeln("  axes:              {$stats['weapons_by_type']['axe']}");
            $output->writeln("  clubs:             {$stats['weapons_by_type']['club']}");
            $output->writeln("  distance:          {$stats['weapons_by_type']['distance']}");
            $output->writeln("  bows:              {$stats['weapons_by_type']['bow']}");
            $output->writeln("  crossbows:         {$stats['weapons_by_type']['crossbow']}");
            $output->writeln("  spears:            {$stats['weapons_by_type']['spear']}");
            $output->writeln("  throwing:          {$stats['weapons_by_type']['throwing']}");
            $output->writeln("Wands/Rods:          {$stats['wands']}");
            $output->writeln("Equipment:           {$stats['equipment']}");
            $output->writeln("Empty Buy JSON:      {$stats['empty_buy']}");
            $output->writeln("Empty Sell JSON:     {$stats['empty_sell']}");
            $output->writeln("Missing group:       {$stats['missing_group']}");
            $output->writeln("Unknown→equipment:   {$stats['routed_equipment_unknown']}");
            if ($warnings) {
                $output->writeln('');
                $output->writeln('<comment>=== Warnings ===</comment>');
                foreach ($warnings as $w) {
                    $output->writeln("<comment>• {$w}</comment>");
                }
            }
            $output->writeln('');
        }

        // Validation stamp
        file_put_contents(rtrim($dstDir, '/\\') . DIRECTORY_SEPARATOR . '.validation.json', json_encode([
            'generatedAt' => date('c'),
            'source' => basename($path),
            'counts' => [
                'weapons'   => count($weapons),
                'wands'     => count($wands),
                'equipment' => count($equip),
            ],
            'stats' => $stats,
            'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Enforce fail-on-warnings
        if ($failOnWarnings && $warnings) {
            $output->writeln('<error>Warnings present and --fail-on-warnings enabled. Failing.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Merchant items generated successfully.</info>');
        return Command::SUCCESS;
    }

    /**
     * @return array<string,int>
     */
    private function parseCityPriceMap(string $json): array
    {
        $json = trim($json);
        if ($json === '') return [];
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $city => $price) {
            if ($price === null || $price === '') continue;
            if (is_numeric($price)) {
                $out[(string)$city] = (int)$price;
            }
        }
        return $out;
    }

    /**
     * @param string|null $weaponType
     * @return bool
     */
    private function isWeapon(?string $weaponType): bool
    {
        if (!$weaponType) return false;
        return in_array($weaponType, ['sword','axe','club','distance','bow','crossbow','spear','throwing'], true);
    }

    /**
     * @param string|null $weaponType
     * @param array $row
     * @return bool
     */
    private function isWandOrRod(?string $weaponType, array $row): bool
    {
        $wt = $weaponType ?? '';
        if (in_array($wt, ['wand','rod'], true)) return true;
        // fallback heuristic: group or name contains “wand/rod”
        $name = strtolower((string)($row['name'] ?? ''));
        $group = strtolower((string)($row['group'] ?? ''));
        return str_contains($group, 'wands') || str_contains($name, 'wand') || str_contains($name, 'rod');
    }

    /**
     * @param string $weaponType
     * @param string $slotType
     * @param array $row
     * @return string|null
     */
    private function deriveGroup(string $weaponType, string $slotType, array $row): ?string
    {
        $wt = strtolower($weaponType);
        switch ($wt) {
            case 'sword':   return 'weapons/swords';
            case 'axe':     return 'weapons/axes';
            case 'club':    return 'weapons/clubs';
            case 'distance':
            case 'bow':
            case 'crossbow':
            case 'spear':
            case 'throwing':return 'weapons/distance';
            case 'wand':
            case 'rod':     return 'wands';
        }
        // equipment buckets
        $st = strtolower($slotType ?? '');
        if (in_array($st, ['head','helmet'], true)) return 'equipment/helmet';
        if (in_array($st, ['armor','body'], true))  return 'equipment/armor';
        if (in_array($st, ['legs'], true))          return 'equipment/legs';
        if (in_array($st, ['feet','boots'], true))  return 'equipment/boots';
        if (in_array($st, ['shield'], true))        return 'equipment/shield';
        if (in_array($st, ['hand','two-hand'], true)) return 'equipment/hand';
        if (in_array($st, ['tool','utility'], true))  return 'equipment/tools';

        // fallback: optional column 'group' if exists in CSV/XLSX
        if (!empty($row['group'])) return (string)$row['group'];
        return null;
    }

    /**
     * Lua wants integer-keyed map like [id] = {...}; keep keys stable
     */
    private function wrapWithKeys(array $assoc): array
    {
        // ensure stable key order
        ksort($assoc, SORT_NUMERIC);
        $out = [];
        foreach ($assoc as $id => $meta) {
            $out[(int)$id] = $meta;
        }
        return $out;
    }
}
