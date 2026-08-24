<?php

namespace App\Support\Settings;

use App\Models\Setting;
use App\Support\Branches\CurrentBranch;

/**
 * Thin façade over the settings table for the current branch, with sane
 * defaults so callers never have to null-check. Values are JSON-cast.
 */
class Settings
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [
        // Clinic identity
        'clinic.name' => 'Skinthera Medical Aesthetic',
        'clinic.address' => 'Tacurong City',
        'clinic.phone' => '',
        'clinic.receipt_footer' => 'Thank you for your visit!',
        // Billing
        'billing.currency' => 'PHP',
        'billing.currency_symbol' => '₱',
        'billing.tax_enabled' => false,
        'billing.tax_rate' => 12,        // percent
        'billing.tax_inclusive' => false,
        // Inventory
        'inventory.expiry_threshold_days' => 30,
        // Backups
        'backup.last_succeeded_at' => null,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Setting::query()->where('key', $key)->first();

        if ($row !== null) {
            return $row->value;
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value, string $group = 'general'): Setting
    {
        return Setting::query()->updateOrCreate(
            ['branch_id' => CurrentBranch::id(), 'key' => $key],
            ['value' => $value, 'group' => $group],
        );
    }

    /** @return array<string, mixed> All effective settings (defaults merged with stored). */
    public static function all(): array
    {
        $stored = Setting::query()->pluck('value', 'key')->toArray();

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function taxEnabled(): bool
    {
        return (bool) self::get('billing.tax_enabled');
    }

    public static function taxRate(): float
    {
        return (float) self::get('billing.tax_rate');
    }

    public static function taxInclusive(): bool
    {
        return (bool) self::get('billing.tax_inclusive');
    }
}
