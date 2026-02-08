<?php

namespace App\Services\Search;

class ListingSearchBuckets
{
    /**
     * @return array<int, array{label: string, min: int, max: int|null}>
     */
    public static function priceBuckets(): array
    {
        return [
            ['label' => '0-300', 'min' => 0, 'max' => 300],
            ['label' => '300-600', 'min' => 300, 'max' => 600],
            ['label' => '600-1000', 'min' => 600, 'max' => 1000],
            ['label' => '1000+', 'min' => 1000, 'max' => null],
        ];
    }

    /**
     * @return array<int, array{label: string, min: int, max: int|null}>
     */
    public static function areaBuckets(): array
    {
        return [
            ['label' => '0-30', 'min' => 0, 'max' => 30],
            ['label' => '30-60', 'min' => 30, 'max' => 60],
            ['label' => '60-100', 'min' => 60, 'max' => 100],
            ['label' => '100+', 'min' => 100, 'max' => null],
        ];
    }

    public static function priceBucketFor(?float $price): ?string
    {
        return self::bucketFor($price, self::priceBuckets());
    }

    public static function areaBucketFor(?float $area): ?string
    {
        return self::bucketFor($area, self::areaBuckets());
    }

    /**
     * @param  array<int, array{label: string, min: int, max: int|null}>  $buckets
     */
    private static function bucketFor(?float $value, array $buckets): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($buckets as $bucket) {
            $min = $bucket['min'];
            $max = $bucket['max'];
            if ($max === null) {
                if ($value >= $min) {
                    return $bucket['label'];
                }
            } elseif ($value >= $min && $value < $max) {
                return $bucket['label'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, array{label: string, min: int, max: int|null}>  $buckets
     * @return array<int, array{min: int, max: int|null}>
     */
    public static function rangesForBuckets(array $labels, array $buckets): array
    {
        $map = [];
        foreach ($buckets as $bucket) {
            $map[$bucket['label']] = ['min' => $bucket['min'], 'max' => $bucket['max']];
        }

        $ranges = [];
        foreach ($labels as $label) {
            if (isset($map[$label])) {
                $ranges[] = $map[$label];
            }
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{label: string, min: int, max: int|null}>  $buckets
     */
    public static function caseExpression(string $column, array $buckets): string
    {
        $cases = [];
        foreach ($buckets as $bucket) {
            $min = $bucket['min'];
            $max = $bucket['max'];
            if ($max === null) {
                $cases[] = "WHEN {$column} >= {$min} THEN '{$bucket['label']}'";
            } else {
                $cases[] = "WHEN {$column} >= {$min} AND {$column} < {$max} THEN '{$bucket['label']}'";
            }
        }

        $casesSql = implode(' ', $cases);

        return "CASE {$casesSql} END";
    }
}
