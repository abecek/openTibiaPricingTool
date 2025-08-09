<?php
declare(strict_types=1);

namespace App\Merchant;

use stdClass;

final class LuaTableDumper
{
    /**
     * Dump any PHP value to a Lua literal.
     * - Arrays (assoc) => { [key]=value, ... }
     * - Arrays (seq 1..n) => { v1, v2, ... }
     * - stdClass (empty) => {}
     * - stdClass (with props) => treat as assoc
     * - Scalars => numbers/strings/bool/nil
     *
     * @param mixed $value
     * @param int $level
     * @return string
     */
    public static function dump(mixed $value, int $level = 0): string
    {
        // Handle stdClass specially (often used to force {} instead of [])
        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);
            if (empty($vars)) {
                return '{}';
            }
            // fallthrough as associative array
            $value = $vars;
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
        if ($arr === []) {
            return '{}';
        }

        $indent = str_repeat('    ', $level);
        $nextIndent = str_repeat('    ', $level + 1);

        $assoc = self::isAssoc($arr);

        $parts = [];
        if ($assoc) {
            foreach ($arr as $k => $v) {
                $key = self::formatKey($k);
                $val = self::dump($v, $level + 1);
                $parts[] = "{$nextIndent}{$key} = {$val}";
            }
        } else {
            // sequential (1..n)
            foreach ($arr as $v) {
                $parts[] = "{$nextIndent}" . self::dump($v, $level + 1);
            }
        }

        return "{\n" . implode(",\n", $parts) . "\n{$indent}}";
    }

    /**
     * @param array $arr
     * @return bool
     */
    private static function isAssoc(array $arr): bool
    {
        // Sequential if keys are exactly 0..n-1 or 1..n
        if ($arr === []) {
            return false;
        }
        $keys = array_keys($arr);

        // Allow 1-based sequences (Lua-like) as sequential
        $isOneBased = ($keys[0] === 1);
        if ($isOneBased) {
            $n = count($keys);
            for ($i = 1; $i <= $n; $i++) {
                if ($keys[$i - 1] !== $i) return true; // not sequential
            }
            return false; // sequential 1..n
        }

        // Allow 0-based too (common in PHP)
        $isZeroBased = ($keys[0] === 0);
        if ($isZeroBased) {
            $n = count($keys);
            for ($i = 0; $i < $n; $i++) {
                if ($keys[$i] !== $i) return true; // not sequential
            }
            return false; // sequential 0..n-1
        }

        return true; // anything else is associative
    }

    /**
     * @param string|int $key
     * @return string
     */
    private static function formatKey($key): string
    {
        if (is_int($key)) {
            return '[' . $key . ']';
        }

        // Lua identifier?
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            // bareword key is fine: key = value
            return $key;
        }

        // Otherwise use ["..."] quoting
        return '["' . self::escapeString((string)$key) . '"]';
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
            // Keep numeric literal as-is
            return (string)$v;
        }
        // strings (and everything else)
        return '"' . self::escapeString((string)$v) . '"';
    }

    /**
     * @param string $s
     * @return string
     */
    private static function escapeString(string $s): string
    {
        // Basic escapes for Lua
        return str_replace(["\\", "\"", "\r", "\n", "\t"], ["\\\\", "\\\"", "\\r", "\\n", "\\t"], $s);
    }
}
