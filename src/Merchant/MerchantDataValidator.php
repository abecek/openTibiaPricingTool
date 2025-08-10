<?php
declare(strict_types=1);

namespace App\Merchant;

final class MerchantDataValidator
{
    /**
     * @param array<int, array<string, mixed>> $rows CSV/XLSX rows
     * @return array<int, string> list of errors
     */
    public function validate(array $rows): array
    {
        $errors = [];
        $required = ['id', 'name']; // minimal set
        $ids = [];

        foreach ($rows as $i => $row) {
            $rowNo = $i + 2; // +2 if headers on row 1
            // required presence
            foreach ($required as $col) {
                if (!array_key_exists($col, $row) || $row[$col] === '' || $row[$col] === null) {
                    $errors[] = "Row {$rowNo}: missing required column '{$col}'.";
                }
            }

            // id
            if (isset($row['id']) && !is_numeric($row['id'])) {
                $errors[] = "Row {$rowNo}: id must be numeric.";
            } elseif (isset($row['id'])) {
                $id = (int)$row['id'];
                if ($id <= 0) {
                    $errors[] = "Row {$rowNo}: id must be > 0.";
                } elseif (isset($ids[$id])) {
                    $errors[] = "Row {$rowNo}: duplicate id {$id} (also on row {$ids[$id]}).";
                } else {
                    $ids[$id] = $rowNo;
                }
            }

            // Buy/Sell JSON sanity
            foreach (['Buy','Sell'] as $col) {
                if (!array_key_exists($col, $row)) {
                    continue; // brak kolumny nie jest błędem krytycznym
                }
                $cell = (string)$row[$col];
                if ($cell === '' || $cell === null) {
                    continue; // pusta OK
                }
                $decoded = json_decode($cell, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "Row {$rowNo}: column '{$col}' is not valid JSON.";
                    continue;
                }
                if (!is_array($decoded)) {
                    $errors[] = "Row {$rowNo}: column '{$col}' must be JSON object {City:int,...}.";
                    continue;
                }
                foreach ($decoded as $city => $price) {
                    if ($price !== null && !is_int($price) && !is_numeric($price)) {
                        $errors[] = "Row {$rowNo}: '{$col}' price for city '{$city}' must be integer or null.";
                    }
                }
            }
        }

        return $errors;
    }
}
