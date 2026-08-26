<?php

namespace App\Support;

class SearchTextNormalizer
{
    public static function normalize(?string $value): string
    {
        $value = mb_convert_kana((string) $value, 'asKVn', 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');

        return preg_replace('/[\s\p{Z}]+/u', '', $value) ?? '';
    }

    public static function searchKey(?string ...$values): string
    {
        return self::normalize(implode(' ', array_filter($values, fn (?string $value): bool => filled($value))));
    }

    /**
     * @return array<int, string>
     */
    public static function searchTerms(?string $value): array
    {
        $value = mb_convert_kana((string) $value, 'asKVn', 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');

        $terms = preg_split('/[\s\p{Z}]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(
            fn (string $term): string => preg_replace('/[\s\p{Z}]+/u', '', $term) ?? '',
            $terms,
        ), fn (string $term): bool => $term !== ''));
    }

    public static function productKey(?string ...$values): string
    {
        return self::searchKey(...$values);
    }
}
