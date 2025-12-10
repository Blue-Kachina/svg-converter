<?php

namespace App\Support;

final class Stats
{
    /**
     * @param float[] $values
     * @return array{count:int,min:float,max:float,avg:float,p50:float,p90:float,p95:float,p99:float}
     */
    public static function summarize(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return [
                'count' => 0,
                'min' => 0.0,
                'max' => 0.0,
                'avg' => 0.0,
                'p50' => 0.0,
                'p90' => 0.0,
                'p95' => 0.0,
                'p99' => 0.0,
            ];
        }
        sort($values);
        $min = $values[0];
        $max = $values[$n - 1];
        $sum = array_sum($values);
        $avg = $sum / $n;
        return [
            'count' => $n,
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
            'p50' => self::percentile($values, 50),
            'p90' => self::percentile($values, 90),
            'p95' => self::percentile($values, 95),
            'p99' => self::percentile($values, 99),
        ];
    }

    /**
     * @param float[] $sortedValues Must be sorted asc
     */
    public static function percentile(array $sortedValues, float $p): float
    {
        $n = count($sortedValues);
        if ($n === 0) return 0.0;
        $rank = ($p / 100) * ($n - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return (float) $sortedValues[$low];
        }
        $weight = $rank - $low;
        return (1 - $weight) * $sortedValues[$low] + $weight * $sortedValues[$high];
    }
}
