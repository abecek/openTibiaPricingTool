<?php
declare(strict_types=1);

namespace App\Pricing;

use RuntimeException;

/**
 * Robust CSV reader for itemsWorkFile_extended.csv
 *
 * - Normalizes headers (trim, lowercase, collapse spaces, strip BOM).
 * - Maps common alias headers to canonical names (Buy, Sell, weaponType, slotType, etc.).
 * - Autodetects separator ("," or ";").
 * - Skips completely empty trailing rows and rows without a 'name'.
 * - Ensures canonical keys exist even if columns are missing (value = null).
 * - Trims string cells.
 * - Casts "id" to int when numeric.
 *
 * NOTE: This reader does NOT decode JSON in Buy/Sell columns.
 *       It returns the raw cell string. Generators/consumers should parse JSON themselves.
 *       Per-city Tibia columns are not required and not special-cased.
 */
final class EquipmentCsvReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(string $path): array
    {
        $fp = @fopen($path, 'r');
        if (!$fp) {
            throw new RuntimeException("Cannot open CSV: {$path}");
        }

        // Autodetect separator from first non-empty line
        $firstLine = '';
        $pos = ftell($fp);
        while (($line = fgets($fp)) !== false) {
            if (trim($line) !== '') { $firstLine = $line; break; }
        }
        $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';
        fseek($fp, $pos);

        $rawHeaders = fgetcsv($fp, 0, $sep);
        if ($rawHeaders === false) {
            fclose($fp);
            return [];
        }

        $headers = $this->normalizeHeaders($rawHeaders);
        $map     = $this->buildHeaderMap($headers);

        $rows = [];
        while (($row = fgetcsv($fp, 0, $sep)) !== false) {
            if ($this->isEffectivelyEmptyCsvRow($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $origHeader) {
                $norm = $this->norm($origHeader);
                $canonical = $map[$norm] ?? null;
                if ($canonical === null) {
                    continue;
                }
                $assoc[$canonical] = $row[$i] ?? null;
            }

            // Ensure canonical keys exist (even if column missing)
            foreach ([
                         'id','name','Buy','Sell',
                         'weaponType','slotType',
                         'Tibia Buy Price','Tibia Sell Price',
                     ] as $k) {
                if (!array_key_exists($k, $assoc)) {
                    $assoc[$k] = null;
                }
            }

            // Trim strings and strip BOM
            foreach ($assoc as $k => $v) {
                if (is_string($v)) {
                    $assoc[$k] = trim($this->stripBom($v));
                }
            }

            // Cast id
            if (is_numeric($assoc['id'] ?? null)) {
                $assoc['id'] = (int)$assoc['id'];
            }

            // Skip rows without a name (noise/technical rows)
            if ($assoc['name'] === null || $assoc['name'] === '') {
                continue;
            }

            // Final emptiness check
            if ($this->isEffectivelyEmptyAssoc($assoc)) {
                continue;
            }

            $rows[] = $assoc;
        }

        fclose($fp);
        return $rows;
    }

    /**
     * Normalize raw headers (trim + strip BOM)
     */
    private function normalizeHeaders(array $raw): array
    {
        $out = [];
        foreach ($raw as $h) {
            $out[] = is_string($h) ? trim($this->stripBom($h)) : $h;
        }
        return $out;
    }

    /**
     * @param string $s
     * @return string
     */
    private function stripBom(string $s): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    }

    /**
     * @param string|null $s
     * @return string
     */
    private function norm(?string $s): string
    {
        if ($s === null) return '';
        return strtolower(preg_replace('/\s+/', ' ', trim($s)));
    }

    /**
     * Map normalized header -> canonical name used by the app.
     * Fuzzy maps any header containing "buy"/"sell" (excluding "tibia") to Buy/Sell.
     */
    private function buildHeaderMap(array $headers): array
    {
        $canonical = [
            'id'                  => 'id',
            'name'                => 'name',
            'buy'                 => 'Buy',
            'sell'                => 'Sell',
            'weapontype'          => 'weaponType',
            'weapon type'         => 'weaponType',
            'slottype'            => 'slotType',
            'slot type'           => 'slotType',
            'tibia buy price'     => 'Tibia Buy Price',
            'tibia sell price'    => 'Tibia Sell Price',
        ];

        $map = [];
        foreach ($headers as $h) {
            $n = $this->norm($h);
            if (isset($canonical[$n])) {
                $map[$n] = $canonical[$n];
            } else {
                $map[$n] = trim((string)$h);
            }
        }

        // Fuzzy add Buy/Sell if missing but present under another friendly name.
        $hasBuy  = in_array('Buy',  $map, true);
        $hasSell = in_array('Sell', $map, true);

        if (!$hasBuy) {
            foreach ($headers as $h) {
                $raw = (string)$h;
                $n = $this->norm($raw);
                if (preg_match('/\bbuy\b/i', $raw) && !preg_match('/tibia/i', $raw)) {
                    $map[$n] = 'Buy';
                }
            }
        }
        if (!$hasSell) {
            foreach ($headers as $h) {
                $raw = (string)$h;
                $n = $this->norm($raw);
                if (preg_match('/\bsell\b/i', $raw) && !preg_match('/tibia/i', $raw)) {
                    $map[$n] = 'Sell';
                }
            }
        }

        return $map;
    }

    /**
     * @param array $row
     * @return bool
     */
    private function isEffectivelyEmptyCsvRow(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && trim((string)$v) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $assoc
     * @return bool
     */
    private function isEffectivelyEmptyAssoc(array $assoc): bool
    {
        foreach ($assoc as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }
        return true;
    }
}
