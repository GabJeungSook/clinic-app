<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Coarse-grained permissions grouped by domain. Nav visibility and route
     * access are gated against these, so rolling out staff accounts is just a
     * matter of role assignment.
     *
     * @var array<int, string>
     */
    protected array $permissions = [
        'patients.view', 'patients.manage',
        'appointments.view', 'appointments.manage',
        'pos.use',
        'services.view', 'services.manage',
        'inventory.view', 'inventory.manage',
        'purchasing.view', 'purchasing.manage',
        'treatments.view', 'treatments.manage',
        'billing.view', 'billing.manage',
        'promotions.manage',
        'reports.view',
        'settings.manage', 'users.manage',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name);
        }

        $roles = [
            // Owner / admin — full access.
            RoleEnum::Owner->value => $this->permissions,

            // Doctor / therapist — clinical + front desk, no admin config.
            RoleEnum::Doctor->value => [
                'patients.view', 'patients.manage',
                'appointments.view', 'appointments.manage',
                'pos.use',
                'services.view', 'inventory.view',
                'treatments.view', 'treatments.manage',
                'billing.view', 'reports.view',
            ],

            // Receptionist — front desk: dashboard, appointments, patients, POS, reports.
            RoleEnum::Receptionist->value => [
                'patients.view', 'patients.manage',
                'appointments.view', 'appointments.manage',
                'pos.use',
                'reports.view',
            ],

            // Inventory clerk — stock and purchasing.
            RoleEnum::InventoryClerk->value => [
                'inventory.view', 'inventory.manage',
                'purchasing.view', 'purchasing.manage',
                'services.view', 'reports.view',
            ],
        ];

        foreach ($roles as $roleName => $grants) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($grants);
        }
    }
}
