<?php

namespace App\Support;

use App\Models\Config;
use Carbon\Carbon;

class RankingCycle
{
    public static function currentQuarter(): array
    {
        $now = now();
        $year = (int) $now->year;
        $index = (int) ceil($now->month / 3);
        $startMonth = (($index - 1) * 3) + 1;

        return [
            'type' => 'quarterly',
            'start' => Carbon::create($year, $startMonth, 1)->startOfDay(),
            'end' => Carbon::create($year, $startMonth + 2, 1)->endOfMonth(),
            'index' => $index,
            'label_en' => sprintf('Q%d %d Tops', $index, $year),
            'label_ar' => sprintf('أوائل الربع %d %d', $index, $year),
        ];
    }

    /**
     * @return array{type: string, start: Carbon, end: Carbon, label_en: string, label_ar: string}
     */
    public static function current(): array
    {
        $config = Config::query()->first();
        $type = $config?->cycle_type ?: 'quarterly';
        $now = now();
        $year = (int) ($config?->cycle_year ?: $now->year);

        [$startMonth, $endMonth, $index] = match ($type) {
            'monthly' => self::monthlyWindow($config, $now),
            'semi_annual' => self::semiAnnualWindow($config, $now),
            'annual' => [1, 12, 1],
            default => self::quarterlyWindow($config, $now),
        };

        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = Carbon::create($year, $endMonth, 1)->endOfMonth();

        return [
            'type' => $type === 'monthly' || $type === 'semi_annual' || $type === 'annual' ? $type : 'quarterly',
            'start' => $start,
            'end' => $end,
            'index' => $index,
            'label_en' => self::labelEn($type === 'monthly' || $type === 'semi_annual' || $type === 'annual' ? $type : 'quarterly', $index, $year),
            'label_ar' => self::labelAr($type === 'monthly' || $type === 'semi_annual' || $type === 'annual' ? $type : 'quarterly', $index, $year),
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function quarterlyWindow(?Config $config, Carbon $now): array
    {
        $index = (int) ($config?->cycle_index ?: (int) ceil($now->month / 3));
        $index = max(1, min(4, $index));
        $start = (($index - 1) * 3) + 1;

        return [$start, $start + 2, $index];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function monthlyWindow(?Config $config, Carbon $now): array
    {
        $index = (int) ($config?->cycle_index ?: $now->month);
        $index = max(1, min(12, $index));

        return [$index, $index, $index];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function semiAnnualWindow(?Config $config, Carbon $now): array
    {
        $index = (int) ($config?->cycle_index ?: ($now->month <= 6 ? 1 : 2));
        $index = max(1, min(2, $index));

        return $index === 1 ? [1, 6, 1] : [7, 12, 2];
    }

    protected static function labelEn(string $type, int $index, int $year): string
    {
        return match ($type) {
            'monthly' => sprintf('Month %d %d Tops', $index, $year),
            'semi_annual' => sprintf('H%d %d Tops', $index, $year),
            'annual' => sprintf('%d Tops', $year),
            default => sprintf('Q%d %d Tops', $index, $year),
        };
    }

    protected static function labelAr(string $type, int $index, int $year): string
    {
        return match ($type) {
            'monthly' => sprintf('أوائل الشهر %d %d', $index, $year),
            'semi_annual' => sprintf('أوائل النصف %d %d', $index, $year),
            'annual' => sprintf('أوائل عام %d', $year),
            default => sprintf('أوائل الربع %d %d', $index, $year),
        };
    }
}
