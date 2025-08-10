<?php
declare(strict_types=1);

namespace App\Merchant;

use stdClass;

final class LuaTableDumper
{
    /**
     * Dump any PHP value to a Lua literal.
     * Keep generic behavior, but for item metas (arrays containing 'buy' or 'sell')
     * print 'buy' and 'sell' on separate indented lines, while the primary fields
     * (id, name, slotType, group, subType) are kept inline on the opening line.
     *
     * @param mixed $value
     * @param int $level
     * @return string
     */
    public static function dump(mixed $value, int $level = 0): string
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            return self::dumpArray($value, $level);
        }

        return self::formatScalar($value);
    }

    /**
     * @param array $arr
     * @param int $level
     * @return string
     */
    private static function dumpArray(array $arr, int $level): string
    {
        // Empty array -> {}
        if ($arr === []) {
            return '{}';
        }

        $indent      = str_repeat('    ', $level);
        $nextIndent  = str_repeat('    ', $level + 1);

        $assoc = self::isAssoc($arr);

        // Special formatting for item metas (have 'buy' or 'sell' keys)
        if ($assoc && self::looksLikeItemMeta($arr)) {
            // Prepare inline head (id, name, slotType, group, subType)
            $headKeys = ['id', 'name', 'slotType', 'group', 'subType'];
            $inlineParts = [];
            $restLines   = [];

            foreach ($headKeys as $hk) {
                if (array_key_exists($hk, $arr)) {
                    $inlineParts[] = $hk . ' = ' . self::dump($arr[$hk], 0);
                    unset($arr[$hk]);
                }
            }

            // build buy/sell lines if present
            foreach (['buy', 'sell'] as $bs) {
                if (array_key_exists($bs, $arr)) {
                    $restLines[] = $nextIndent . $bs . ' = ' . self::formatInlineAssoc($arr[$bs]);
                    unset($arr[$bs]);
                }
            }

            // Any other remaining keys: generic formatting, one per line
            if (!empty($arr)) {
                ksort($arr);
                foreach ($arr as $k => $v) {
                    $restLines[] = $nextIndent . self::formatKey($k) . ' = ' . self::dump($v, $level + 1);
                }
            }

            // Compose
            $out = '{ ';
            if (!empty($inlineParts)) {
                $out .= implode(', ', $inlineParts);
            }
            if (!empty($restLines)) {
                $out .= ",\n" . implode(",\n", $restLines) . "\n" . $indent . '}';
            } else {
                $out .= ' }';
            }
            return $out;
        }

        // Generic formatting
        $parts = [];
        if ($assoc) {
            foreach ($arr as $k => $v) {
                $parts[] = "{$nextIndent}" . self::formatKey($k) . ' = ' . self::dump($v, $level + 1);
            }
        } else {
            foreach ($arr as $v) {
                $parts[] = "{$nextIndent}" . self::dump($v, $level + 1);
            }
        }

        return "{\n" . implode(",\n", $parts) . "\n{$indent}}";
    }

    /**
     * Heuristic: array that has 'buy' or 'sell' is considered an item meta
     *
     * @param array $arr
     * @return bool
     */
    private static function looksLikeItemMeta(array $arr): bool
    {
        return array_key_exists('buy', $arr) || array_key_exists('sell', $arr);
    }

    /**
     * Format associative array inline: { Key1 = Val1, Key2 = Val2, ... }
     *
     * @param mixed $value
     * @return string
     */
    private static function formatInlineAssoc(mixed $value): string
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }
        if (!is_array($value) || $value === []) {
            return '{}';
        }
        $arr = $value;
        ksort($arr);
        $pairs = [];
        foreach ($arr as $k => $v) {
            $pairs[] = self::formatLuaKeyInline($k) . ' = ' . self::dump($v, 0);
        }
        return '{ ' . implode(', ', $pairs) . ' }';
    }

    /**
     * @param array $arr
     * @return bool
     */
    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        $keys = array_keys($arr);

        // Allow 1-based sequences (Lua-like)
        $isOneBased = ($keys[0] === 1);
        if ($isOneBased) {
            $n = count($keys);
            for ($i = 1; $i <= $n; $i++) {
                if ($keys[$i - 1] !== $i) return true;
            }
            return false;
        }

        // Allow 0-based sequences (PHP-like)
        $isZeroBased = ($keys[0] === 0);
        if ($isZeroBased) {
            $n = count($keys);
            for ($i = 0; $i < $n; $i++) {
                if ($keys[$i] !== $i) return true;
            }
            return false;
        }

        return true;
    }

    /**
     * @param string|int $key
     * @return string
     */
    private static function formatKey(mixed$key): string
    {
        if (is_int($key)) {
            return '[' . $key . ']';
        }
        $s = (string)$key;
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s)) {
            return $s;
        }
        return '["' . self::escapeString($s) . '"]';
    }

    /**
     * @param mixed $v
     * @return string
     */
    private static function formatScalar(mixed $v): string
    {
        if ($v === null)   return 'nil';
        if ($v === true)   return 'true';
        if ($v === false)  return 'false';
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        return '"' . self::escapeString((string)$v) . '"';
    }

    /**
     * @param mixed $key
     * @return string
     */
    private static function formatLuaKeyInline(mixed $key): string
    {
        if (is_int($key)) {
            return '[' . $key . ']';
        }
        $s = (string)$key;
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s)) {
            return $s;
        }
        return '["' . self::escapeString($s) . '"]';
    }

    /**
     * @param string $s
     * @return string
     */
    private static function escapeString(string $s): string
    {
        return str_replace(
            ["\\", "\"", "\r", "\n", "\t"],
            ["\\\\", "\\\"", "\\r", "\\n", "\\t"],
            $s
        );
    }
}
