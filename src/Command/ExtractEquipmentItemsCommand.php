<?php
declare(strict_types=1);

namespace App\Command;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reads TFS items.xml and writes a normalized CSV with a canonical header order,
 * adding extra columns: Url, Level, Tibia Buy/Sell Price, Is Missing Tibia source, Buy, Sell, LootFrom.
 *
 * Example:
 *  php console extract:items-xml \
 *      --input-xml=data/input/items.xml \
 *      --output-csv=data/input/itemsWorkFile.csv \
 *      --debug
 */
class ExtractEquipmentItemsCommand extends AbstractCommand
{
    protected static $defaultName = 'extract:items-xml';

    /** @var array Allows to skip items with specific item ids */
    private const array EXCLUDED_IDS = [ // elemental weapons, equipped rings, etc...
        2202,
        2203,
        2204,
        2205,
        2206,
        2210,
        2211,
        2212,
        2213,
        2214,
        2215,
        2216,

        7744,
        7745,
        7746,
        7747,
        7748,
        7749,
        7750,
        7751,
        7752,
        7753,
        7754,
        7755,
        7756,
        7757,
        7758,

        7763,
        7764,
        7765,
        7766,
        7767,
        7768,
        7769,
        7770,
        7771,
        7772,
        7773,
        7774,
        7775,
        7776,
        7777,

        7838,
        7839,
        7840,
        7850,

        7854,
        7855,
        7856,
        7857,

        7859,
        7860,
        7861,
        7862,
        7863,

        7864,
        7865,
        7866,
        7867,
        7868,
        7869,
        7870,
        7871,
        7872,
        7873,
        7874,
        7875,
        7876,
        7877,
        7878,

        7879,
        7880,
        7881,
        7882,
        7883
    ];

    /** Canonical header order for the output CSV. */
    private const ORDERED_HEADERS = [
        'id','name','Image','slotType','weaponType',
        'Url','Level','Tibia Buy Price','Tibia Sell Price','Is Missing Tibia source',
        'Buy','Sell','LootFrom',
        'absorbPercentAll','absorbPercentDeath','absorbPercentEarth','absorbPercentEnergy','absorbPercentFire',
        'absorbPercentHoly','absorbPercentIce','absorbPercentLifeDrain','absorbPercentManaDrain','absorbPercentPhysical',
        'absorbPercentPoison','allowpickupable','ammoType','armor','attack','blockprojectile','charges','containerSize',
        'corpseType','decayTo','defense','description','destroyTo','duration','effect','elementEarth','elementEnergy',
        'elementFire','elementIce','extradef','femaleTransformTo','field','floorchange','fluidSource','fluidcontainer',
        'forceSerialize','healthGain','healthTicks','hitChance','invisible','levelDoor','magiclevelpoints',
        'maleTransformTo','manaGain','manaTicks','manashield','maxHitChance','maxTextLen','partnerDirection','range',
        'readable','replaceable','rotateTo','runeSpellName','shootType','showattributes','showcharges','showcount',
        'showduration','skillAxe','skillClub','skillDist','skillFist','skillShield','skillSword','speed','stopduration',
        'suppressDrown','suppressDrunk','transformDeEquipTo','transformEquipTo','transformTo','type','weight','worth',
        'writeOnceItemId','writeable',
    ];

    /** Columns that default to JSON empty list "[]". */
    private const JSONish_COLUMNS = [
        'Tibia Buy Price', 'Tibia Sell Price', 'Buy', 'Sell', 'LootFrom',
    ];

    protected function configure(): void
    {
        $this
            ->addOption(
                'input-xml',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to TFS items.xml'
            )
            ->addOption(
                'output-csv',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to normalized CSV output'
            )
            ->addOption(
                'delimiter',
                null,
                InputOption::VALUE_OPTIONAL,
                'CSV delimiter',
                ';'
            )
            ->addOption(
                'guess-image',
                null,
                InputOption::VALUE_NONE,
                'Fill Image as images/{name}.gif (lowercase)')
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
        $xmlPath = (string)$input->getOption('input-xml');
        $csvPath = (string)$input->getOption('output-csv');
        $delim = (string)$input->getOption('delimiter');
        $guessImg = (bool)$input->getOption('guess-image');
        $debug = (bool)$input->getOption('debug');

        if ($xmlPath === '' || !is_file($xmlPath)) {
            throw new RuntimeException("Input XML not found: {$xmlPath}");
        }
        if ($csvPath === '') {
            throw new RuntimeException("Output CSV path is required (use --output-csv=...)");
        }

        // Load XML
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        if (!@$doc->load($xmlPath)) {
            throw new RuntimeException("Failed to load/parse XML: {$xmlPath}");
        }
        $xp = new DOMXPath($doc);

        /** @var DOMElement[] $itemNodes */
        $itemNodes = iterator_to_array($xp->query('//item')) ?: [];

        $rows = [];
        $exported = 0;

        foreach ($itemNodes as $item) {
            $row = array_fill_keys(self::ORDERED_HEADERS, '');

            $id = trim((string)$item->getAttribute('id'));
            $name = trim((string)$item->getAttribute('name'));
            $type = trim((string)$item->getAttribute('type')); // optional in TFS

            if ($id === '' || $name === '') {
                // Skip weird/incomplete entries
                continue;
            }

            if (in_array((int) $id, self::EXCLUDED_IDS)) {
                continue;
            }

            $row['id']   = $id;
            $row['name'] = $name;
            if ($type !== '') {
                $row['type'] = $type;
            }

            // Collect <attribute key="..." value="..."/>
            foreach ($xp->query('./attribute', $item) as $attrEl) {
                /** @var DOMElement $attrEl */
                $key = trim((string)$attrEl->getAttribute('key'));
                $val = (string)$attrEl->getAttribute('value');

                if ($key === '') {
                    continue;
                }
                if (array_key_exists($key, $row)) {
                    $row[$key] = $val;
                } else {
                    // Keys not in ORDERED_HEADERS are ignored on purpose
                }
            }

            // Filter like original script (only items with slotType or weaponType)
            if (($row['slotType'] === '' && $row['weaponType'] === '')) {
                continue;
            }

            // Fill extra columns defaults
            if ($guessImg && $row['Image'] === '') {
                // images/{name}.gif ; keep spaces, lowercase like your scraper
                $row['Image'] = 'images/' . mb_strtolower($row['name']) . '.gif';
            }

            if ($row['Url'] === '') {
                // Pre-fill Tibia wiki URL pattern; can be later overwritten by scraper
                $urlName = str_replace(' ', '_', ucwords($row['name']));
                $row['Url'] = 'https://tibia.fandom.com/wiki/' . $urlName;
            }

            if ($row['Level'] === '') {
                $row['Level'] = '0'; // default example shows 0 for Small Stone
            }

            foreach (self::JSONish_COLUMNS as $jsonKey) {
                if ($row[$jsonKey] === '') {
                    $row[$jsonKey] = '[]';
                }
            }

            $rows[] = $row;
            $exported++;
        }

        // Write CSV
        $fh = fopen($csvPath, 'w');
        if (!$fh) {
            throw new RuntimeException("Unable to open output: {$csvPath}");
        }

        // Header
        fputcsv($fh, self::ORDERED_HEADERS, $delim);

        foreach ($rows as $r) {
            fputcsv($fh, self::rowToValues($r), $delim);
        }
        fclose($fh);

        if ($debug) {
            $output->writeln(sprintf('<info>Exported %d items → %s</info>', $exported, $csvPath));
        } else {
            $output->writeln('<info>Output written: ' . $csvPath . '</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array $assoc
     * @return array<int,string>
     */
    private static function rowToValues(array $assoc): array
    {
        $vals = [];
        foreach (self::ORDERED_HEADERS as $h) {
            $vals[] = $assoc[$h] ?? '';
        }
        return $vals;
    }
}
