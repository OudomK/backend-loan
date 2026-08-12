<?php

namespace App\Support;

final class SearchResultRanker
{
    /**
     * Rank an autocomplete result: exact match, prefix match, partial match.
     * Spaces and punctuation are ignored as a secondary comparison so codes,
     * phone numbers, and identity numbers work while the user is still typing.
     *
     * @param  array<int, mixed>  $values
     */
    public static function score(string $query, array $values): int
    {
        $needle = self::normalize($query);
        $compactNeedle = self::compact($needle);

        if ($needle === '') {
            return 3;
        }

        $normalizedValues = array_map(fn (mixed $value): array => [
            self::normalize((string) ($value ?? '')),
            self::compact(self::normalize((string) ($value ?? ''))),
        ], $values);

        foreach ($normalizedValues as [$value, $compactValue]) {
            if ($value === $needle || ($compactNeedle !== '' && $compactValue === $compactNeedle)) {
                return 0;
            }
        }

        foreach ($normalizedValues as [$value, $compactValue]) {
            if (str_starts_with($value, $needle)
                || ($compactNeedle !== '' && str_starts_with($compactValue, $compactNeedle))) {
                return 1;
            }
        }

        foreach ($normalizedValues as [$value, $compactValue]) {
            if (str_contains($value, $needle)
                || ($compactNeedle !== '' && str_contains($compactValue, $compactNeedle))) {
                return 2;
            }
        }

        return 3;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '');
    }

    private static function compact(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', $value) ?? '';
    }
}
