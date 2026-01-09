<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@sipera.baliprov.go.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ]
        );
        $admin->assignRole(UserRole::ADMIN->value);

        // Create Kadis user
        $kadis = User::firstOrCreate(
            ['email' => 'kadis@sipera.baliprov.go.id'],
            [
                'name' => 'Kepala Dinas',
                'password' => Hash::make('password'),
                'role' => UserRole::KADIS,
                'is_active' => true,
            ]
        );
        $kadis->assignRole(UserRole::KADIS->value);

        // Create Bendahara user
        $bendahara = User::firstOrCreate(
            ['email' => 'bendahara@sipera.baliprov.go.id'],
            [
                'name' => 'Bendahara',
                'password' => Hash::make('password'),
                'role' => UserRole::BENDAHARA,
                'is_active' => true,
            ]
        );
        $bendahara->assignRole(UserRole::BENDAHARA->value);

        // Create Tim Perencanaan user
        $timPerencanaan = User::firstOrCreate(
            ['email' => 'perencanaan@sipera.baliprov.go.id'],
            [
                'name' => 'Tim Perencanaan',
                'password' => Hash::make('password'),
                'role' => UserRole::TIM_PERENCANAAN,
                'is_active' => true,
            ]
        );
        $timPerencanaan->assignRole(UserRole::TIM_PERENCANAAN->value);

        // Create Tim Pelaksana user
        $timPelaksana = User::firstOrCreate(
            ['email' => 'pelaksana@sipera.baliprov.go.id'],
            [
                'name' => 'Tim Pelaksana',
                'password' => Hash::make('password'),
                'role' => UserRole::TIM_PELAKSANA,
                'is_active' => true,
            ]
        );
        $timPelaksana->assignRole(UserRole::TIM_PELAKSANA->value);

        // Create Monev user
        $monev = User::firstOrCreate(
            ['email' => 'monev@sipera.baliprov.go.id'],
            [
                'name' => 'Tim Monitoring & Evaluasi',
                'password' => Hash::make('password'),
                'role' => UserRole::MONEV,
                'is_active' => true,
            ]
        );
        $monev->assignRole(UserRole::MONEV->value);
    }
}
