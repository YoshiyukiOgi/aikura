<?php

namespace App\Services;

use RuntimeException;

class AccessProductNameFormatter
{
    /**
     * @return array{name: string, display_name: string, search_key: string}
     */
    public function format(array $source, ?array $main): array
    {
        $name = $this->composeName(
            $main['主商品名'] ?? null,
            $source['商品名'] ?? null,
        );
        if ($name === null) {
            throw new RuntimeException('Accessの商品名と主商品名がどちらも空です。');
        }

        $capacity = $this->numericOrNull($source['容量(ml)'] ?? null);
        $unit = $this->nullIfBlank($source['容量単位'] ?? null);
        $style = $this->nullIfBlank($source['商品サブネーム'] ?? null);
        $capacityLabel = $capacity !== null && $unit !== null
            ? $this->formatNumber($capacity).$unit
            : null;
        $displayName = $this->joinText($name, $style, $capacityLabel) ?? $name;

        return [
            'name' => $name,
            'display_name' => $displayName,
            'search_key' => $this->joinText($name, $displayName, $style) ?? $displayName,
        ];
    }

    public function composeName(mixed $mainName, mixed $itemName): ?string
    {
        $mainName = $this->nullIfBlank($mainName);
        $itemName = $this->nullIfBlank($itemName);

        if ($mainName === null || $itemName === null) {
            return $mainName ?? $itemName;
        }
        if (str_contains($itemName, $mainName)) {
            return $itemName;
        }
        if (str_contains($mainName, $itemName)) {
            return $mainName;
        }

        $separator = preg_match('/^[<＜\(（\[［【〔「『]/u', $itemName) === 1 ? '' : ' ';

        return $mainName.$separator.$itemName;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function formatNumber(float $value): string
    {
        return fmod($value, 1.0) === 0.0
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', (string) ($value ?? '')) ?? '';

        return $value === '' ? null : $value;
    }

    private function joinText(mixed ...$values): ?string
    {
        $values = array_values(array_filter(array_map($this->nullIfBlank(...), $values)));

        return $values === [] ? null : implode(' ', $values);
    }
}
