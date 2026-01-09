<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Master data
            'master.view',
            'master.create',
            'master.update',
            'master.delete',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Planning
            'planning.view',
            'planning.create',
            'planning.update',
            'planning.delete',

            // Realization
            'realization.view',
            'realization.create',
            'realization.update',
            'realization.delete',
            'realization.submit',

            // Approval
            'approval.view',
            'approval.verify',
            'approval.approve',
            'approval.reject',

            // Reports
            'reports.view',
            'reports.export',

            // Dashboard
            'dashboard.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $rolePermissions = [
            UserRole::ADMIN->value => $permissions, // All permissions

            UserRole::KADIS->value => [
                'dashboard.view',
                'master.view',
                'planning.view',
                'realization.view',
                'approval.view',
                'approval.approve',
                'approval.reject',
                'reports.view',
                'reports.export',
            ],

            UserRole::TIM_PERENCANAAN->value => [
                'dashboard.view',
                'master.view',
                'planning.view',
                'planning.create',
                'planning.update',
                'reports.view',
            ],

            UserRole::TIM_PELAKSANA->value => [
                'dashboard.view',
                'master.view',
                'planning.view',
                'realization.view',
                'realization.create',
                'realization.update',
                'realization.submit',
                'reports.view',
            ],

            UserRole::BENDAHARA->value => [
                'dashboard.view',
                'master.view',
                'planning.view',
                'realization.view',
                'approval.view',
                'approval.verify',
                'approval.reject',
                'reports.view',
                'reports.export',
            ],

            UserRole::MONEV->value => [
                'dashboard.view',
                'master.view',
                'planning.view',
                'realization.view',
                'reports.view',
                'reports.export',
            ],

            UserRole::VIEWER->value => [
                'dashboard.view',
                'master.view',
                'planning.view',
                'realization.view',
                'reports.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
