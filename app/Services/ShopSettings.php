<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Owner-editable shop settings stored in the database, falling back to config defaults.
 */
class ShopSettings
{
    public function openTime(): string
    {
        return $this->value('open_time', config('queue_shop.booking.open_time'));
    }

    public function closeTime(): string
    {
        return $this->value('close_time', config('queue_shop.booking.close_time'));
    }

    public function minutesPerCut(): int
    {
        return (int) $this->value('minutes_per_cut', config('queue_shop.minutes_per_cut'));
    }

    /**
     * @return array<int, int> Carbon day numbers (0 = Sunday)
     */
    public function weeklyClosedDays(): array
    {
        $stored = Setting::where('key', 'weekly_closed_days')->value('value');

        if ($stored === null) {
            return config('queue_shop.booking.weekly_closed_days');
        }

        return array_map('intval', array_filter(explode(',', $stored), fn (string $day) => $day !== ''));
    }

    /**
     * @param  array{open_time: string, close_time: string, minutes_per_cut: int}  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }

    /**
     * @param  array<int, int>  $days  Carbon day numbers (0 = Sunday)
     */
    public function setWeeklyClosedDays(array $days): void
    {
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        Setting::updateOrCreate(['key' => 'weekly_closed_days'], ['value' => implode(',', $days)]);
    }

    private function value(string $key, string|int $default): string
    {
        return (string) (Setting::where('key', $key)->value('value') ?? $default);
    }
}
