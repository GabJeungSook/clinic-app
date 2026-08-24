<?php

namespace App\Enums;

/**
 * Application roles (backed by spatie/laravel-permission). Defined now even
 * though v1 is small, so policies and the future online multi-user rollout
 * have stable role names to gate against.
 */
enum Role: string
{
    case Owner = 'owner';
    case Doctor = 'doctor';
    case Receptionist = 'receptionist';
    case InventoryClerk = 'inventory_clerk';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Doctor => 'Doctor / Therapist',
            self::Receptionist => 'Receptionist',
            self::InventoryClerk => 'Inventory Clerk',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
